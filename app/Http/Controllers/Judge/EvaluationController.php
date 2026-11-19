<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Services\EvaluationCorrectionService;
use App\Services\JudgingPeriod;
use App\Services\ScoringService;
use App\Support\Rubric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly JudgingPeriod $period,
        private readonly EvaluationCorrectionService $corrections,
    ) {}

    public function show(Request $request, Category $category, Finalist $finalist): View
    {
        $this->assertFinalistMatchesCategory($category, $finalist);

        $judge = $request->user();
        $criteria = $category->activeCriteria()->get();

        $evaluation = Evaluation::query()
            ->where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        $scores = $evaluation
            ? $evaluation->scores()->pluck('score', 'criterion_id')->all()
            : [];

        $contributions = $this->scoring->contributions($criteria, $scores);

        // Payload for the frontend scoring module (id, weight and name only).
        $criteriaPayload = $criteria->map(fn ($criterion) => [
            'id' => (string) $criterion->id,
            'weight' => (float) $criterion->weight,
            'name' => $criterion->name,
        ])->values();

        // A host-relative URL keeps the request on the host and port the judge
        // is currently using (an absolute URL would break for a different host).
        // route(..., false) drops the sub-path the app is served from (Apache
        // Alias sub-path), so the request base path is put back in front.
        $autosaveUrl = $request->getBaseUrl().route('judge.autosave', [$category, $finalist], false);

        return view('judge.evaluate', [
            'judge' => $judge,
            'category' => $category,
            'finalist' => $finalist,
            'criteria' => $criteria,
            'criteriaPayload' => $criteriaPayload,
            'autosaveUrl' => $autosaveUrl,
            'evaluation' => $evaluation,
            'scores' => $scores,
            'comment' => $evaluation?->comment,
            'contributions' => $contributions,
            'previewTotal' => array_sum($contributions),
            'weightTotal' => $this->scoring->weightTotal($category),
            'judgingOpen' => $this->period->isOpen(),
            'guide' => Rubric::interpretationGuide(),
        ]);
    }

    /**
     * Autosave a draft. The server is the authoritative calculator: it
     * validates every score, stores it and returns the recalculated
     * contribution of every criterion plus the weighted total.
     */
    public function autosave(Request $request, Category $category, Finalist $finalist): JsonResponse
    {
        $this->assertFinalistMatchesCategory($category, $finalist);

        $judge = $request->user();

        $existing = Evaluation::query()
            ->where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        if ($existing && ! $existing->isDraft()) {
            return response()->json([
                'ok' => false,
                'read_only' => true,
                'status' => $existing->status,
                'message' => 'This evaluation has already been submitted and can no longer be changed.',
            ], 409);
        }

        $criteria = $category->activeCriteria()->get();
        $validIds = $criteria->pluck('id')->map(fn ($id) => (string) $id)->all();

        try {
            $data = $request->validate([
                'scores' => ['present', 'array'],
                'scores.*' => ['nullable', 'integer', 'min:'.ScoringService::MIN_SCORE, 'max:'.ScoringService::MAX_SCORE],
                'comment' => ['nullable', 'string', 'max:5000'],
            ]);
        } catch (ValidationException $exception) {
            // Development gets the exact reason, production only a safe message.
            $messages = collect($exception->errors())->flatten()->filter()->values();

            return response()->json([
                'ok' => false,
                'message' => config('app.debug') && $messages->isNotEmpty()
                    ? 'Invalid scores: '.$messages->implode(' ')
                    : 'The submitted scores are invalid.',
                'errors' => config('app.debug') ? $exception->errors() : [],
            ], 422);
        }

        $cleanScores = [];
        $clearedScores = [];

        foreach ($data['scores'] as $criterionId => $score) {
            if (! in_array((string) $criterionId, $validIds, true)) {
                continue; // ignore criteria that do not belong to this category
            }

            if ($score === null || $score === '') {
                // An explicitly cleared criterion becomes incomplete again.
                $clearedScores[] = (int) $criterionId;

                continue;
            }

            // Guard against values that are outside the 1..5 range. The
            // request validation already rejects them; this is a second
            // layer and never silently converts an invalid score.
            $score = (int) $score;
            if ($score < ScoringService::MIN_SCORE || $score > ScoringService::MAX_SCORE) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Score for criterion '.$criterionId.' must be between 1 and 5.',
                ], 422);
            }

            $cleanScores[(int) $criterionId] = $score;
        }

        $evaluation = DB::transaction(function () use ($judge, $category, $finalist, $cleanScores, $clearedScores, $data, $criteria) {
            $evaluation = Evaluation::firstOrCreate(
                [
                    'judge_id' => $judge->id,
                    'category_id' => $category->id,
                    'finalist_id' => $finalist->id,
                ],
                [
                    'status' => Evaluation::STATUS_DRAFT,
                ]
            );

            if (! $evaluation->isDraft()) {
                return $evaluation;
            }

            foreach ($cleanScores as $criterionId => $score) {
                $evaluation->scores()->updateOrCreate(
                    ['criterion_id' => $criterionId],
                    ['score' => $score]
                );
            }

            foreach ($clearedScores as $criterionId) {
                $evaluation->scores()->where('criterion_id', $criterionId)->delete();
            }

            $evaluation->comment = $data['comment'] ?? null;

            $current = $evaluation->scores()->pluck('score', 'criterion_id')->all();
            $scoredCount = count(array_intersect(array_keys($current), $criteria->pluck('id')->all()));

            // The persisted total is only meaningful once every criterion is
            // scored; until then it stays null and the evaluation is incomplete.
            $evaluation->total_score = $scoredCount === $criteria->count()
                ? $this->scoring->weightedTotal($criteria, $current)
                : null;

            $evaluation->save();

            return $evaluation;
        });

        $persistedScores = $evaluation->scores()->pluck('score', 'criterion_id')->all();
        $contributions = $this->scoring->contributions($criteria, $persistedScores);
        $scoredCount = count(array_intersect(array_keys($persistedScores), $criteria->pluck('id')->all()));

        return response()->json([
            'ok' => true,
            'saved_at' => \App\Support\DateTimeDisplay::iso(now()),
            'saved_at_display' => \App\Support\DateTimeDisplay::format(now()),
            'status' => $evaluation->status,
            'read_only' => false,
            'scores' => $persistedScores,
            'contributions' => $contributions,
            'total_score' => $evaluation->total_score,
            'preview_total' => array_sum($contributions),
            'scored_count' => $scoredCount,
            'criteria_count' => $criteria->count(),
            'complete' => $scoredCount === $criteria->count(),
            'message' => 'Draft saved.',
        ]);
    }

    public function submit(Request $request, Category $category, Finalist $finalist): RedirectResponse
    {
        $this->assertFinalistMatchesCategory($category, $finalist);

        $judge = $request->user();
        $criteria = $category->activeCriteria()->get();

        $evaluation = Evaluation::query()
            ->where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        if ($evaluation && ! $evaluation->isDraft()) {
            return redirect()->route('judge.evaluate', [$category, $finalist])
                ->with('error', 'This evaluation has already been submitted and can no longer be changed.');
        }

        $missing = [];
        $scores = $evaluation ? $evaluation->scores()->pluck('score', 'criterion_id')->all() : [];

        foreach ($criteria as $criterion) {
            $value = $scores[$criterion->id] ?? null;

            if ($value === null || ! $this->scoring->isValidScore($value)) {
                $missing[] = $criterion->name;
            }
        }

        if ($missing !== []) {
            return back()->with('error', 'Please score all '.$criteria->count().' criteria before submitting. Missing: '.implode(', ', $missing));
        }

        $total = $this->scoring->weightedTotal($criteria, $scores);

        DB::transaction(function () use ($evaluation, $judge, $category, $finalist, $scores, $total) {
            $evaluation = Evaluation::firstOrCreate(
                [
                    'judge_id' => $judge->id,
                    'category_id' => $category->id,
                    'finalist_id' => $finalist->id,
                ],
                ['status' => Evaluation::STATUS_DRAFT]
            );

            foreach ($scores as $criterionId => $score) {
                $evaluation->scores()->updateOrCreate(
                    ['criterion_id' => $criterionId],
                    ['score' => (int) $score]
                );
            }

            $evaluation->update([
                'status' => Evaluation::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'total_score' => $total,
            ]);
        });

        return redirect()->route('judge.submitted', [$category, $finalist])
            ->with('status', 'Your evaluation has been submitted.');
    }

    /**
     * Correct an already submitted evaluation of the judge.
     *
     * The judge may only correct their OWN evaluation and only while the
     * judging period is open (enforced by the "judging.open" middleware).
     * The corrected evaluation keeps its submitted status.
     *
     * The endpoint answers with JSON when the request expects it, so that a
     * failed save keeps the unsaved edits on the screen.
     */
    public function correct(Request $request, Category $category, Finalist $finalist): RedirectResponse|JsonResponse
    {
        $this->assertFinalistMatchesCategory($category, $finalist);

        $judge = $request->user();
        $evaluation = $this->ownEvaluation($judge, $category, $finalist);

        if (! $evaluation) {
            return $this->correctionError($request, 'There is no evaluation of this finalist that could be corrected.');
        }

        if (! $evaluation->isSubmitted()) {
            return $this->correctionError($request, 'Only a submitted evaluation can be corrected.');
        }

        if ($category->resultRelease()->exists()) {
            return $this->correctionError($request, 'The official result of this category has already been released. Please contact the administrator to correct this evaluation.');
        }

        // A judge does not need to enter a reason; an optional one is accepted
        // and stored in the audit log when it is given.
        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'integer', 'min:'.ScoringService::MIN_SCORE, 'max:'.ScoringService::MAX_SCORE],
            'comment' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $evaluation = $this->corrections->correct(
                actor: $judge,
                evaluation: $evaluation,
                scores: $data['scores'],
                comment: $data['comment'] ?? null,
                reason: $data['reason'] ?? null,
                actorRole: EvaluationCorrectionService::ACTOR_JUDGE,
            );
        } catch (ValidationException $exception) {
            return $this->correctionError(
                $request,
                'Your corrections could not be saved. Please score all six criteria.',
                $exception->errors(),
                422
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $evaluation->status,
                'scores' => $evaluation->scores()->pluck('score', 'criterion_id')->all(),
                'total_score' => $evaluation->total_score !== null ? (float) $evaluation->total_score : null,
                'saved_at' => \App\Support\DateTimeDisplay::iso($evaluation->submitted_at),
                'saved_at_display' => \App\Support\DateTimeDisplay::format($evaluation->submitted_at),
                'message' => 'Your corrections have been saved.',
            ]);
        }

        return redirect()->route('judge.evaluate', [$category, $finalist])
            ->with('status', 'Your corrections have been saved. The evaluation is submitted again with the updated scores.');
    }

    /**
     * Reset the scores and the feedback of the judge's own evaluation.
     *
     * The evaluation returns to the draft state, so it no longer counts as
     * submitted and has to be scored and submitted again. A reason is
     * optional: the confirmation dialog is the safeguard.
     */
    public function reset(Request $request, Category $category, Finalist $finalist): RedirectResponse
    {
        $this->assertFinalistMatchesCategory($category, $finalist);

        $judge = $request->user();
        $evaluation = $this->ownEvaluation($judge, $category, $finalist);

        if (! $evaluation) {
            return back()->with('error', 'There is no evaluation of this finalist that could be reset.');
        }

        if ($category->resultRelease()->exists()) {
            return back()->with('error', 'The official result of this category has already been released. Please contact the administrator to reset this evaluation.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->corrections->reset(
            actor: $judge,
            evaluation: $evaluation,
            actionType: AuditLog::ACTION_RESET_SCORES,
            reason: $data['reason'] ?? null,
            actorRole: EvaluationCorrectionService::ACTOR_JUDGE,
        );

        return redirect()->route('judge.evaluate', [$category, $finalist])
            ->with('status', 'Your scores and feedback have been reset. The evaluation is Not Started again - score all criteria and submit it anew.');
    }

    /**
     * A failed correction answers with JSON (so the edits stay on screen) and
     * with a redirect for a normal form post.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    private function correctionError(Request $request, string $message, array $errors = [], int $status = 403): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        return back()->withErrors($errors)->with('error', $message);
    }

    public function submitted(Request $request, Category $category, Finalist $finalist): View
    {
        $this->assertFinalistMatchesCategory($category, $finalist);

        $evaluation = Evaluation::query()
            ->where('judge_id', $request->user()->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        $criteria = $category->activeCriteria()->get();
        $scores = $evaluation ? $evaluation->scores()->pluck('score', 'criterion_id')->all() : [];

        return view('judge.submitted', [
            'category' => $category,
            'finalist' => $finalist,
            'evaluation' => $evaluation,
            'criteria' => $criteria,
            'scores' => $scores,
            'contributions' => $this->scoring->contributions($criteria, $scores),
        ]);
    }

    private function assertFinalistMatchesCategory(Category $category, Finalist $finalist): void
    {
        abort_unless($finalist->category_id === $category->id, 404);
    }

    /**
     * The evaluation of the given judge for this category and finalist.
     *
     * Only the judge's own evaluation is ever returned, so a judge can never
     * reach another judge's evaluation.
     */
    private function ownEvaluation(User $judge, Category $category, Finalist $finalist): ?Evaluation
    {
        return Evaluation::query()
            ->where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();
    }
}
