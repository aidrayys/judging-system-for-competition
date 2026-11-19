<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EvaluationCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EvaluationCorrectionService $corrections,
    ) {}

    public function index(Request $request): View
    {
        $query = Evaluation::query()->with(['judge', 'category', 'finalist', 'scores.criterion']);

        if ($request->filled('judge')) {
            $query->where('judge_id', $request->integer('judge'));
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('finalist')) {
            $query->where('finalist_id', $request->integer('finalist'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.evaluations.index', [
            'evaluations' => $query->orderBy('category_id')->orderBy('finalist_id')->orderBy('judge_id')->paginate(30)->withQueryString(),
            'judges' => User::where('role', User::ROLE_JUDGE)->orderBy('name')->get(),
            'categories' => Category::orderBy('display_order')->get(),
            'finalists' => Finalist::orderBy('code')->get(),
        ]);
    }

    public function show(Evaluation $evaluation): View
    {
        $evaluation->load(['judge', 'category', 'finalist', 'scores.criterion']);

        return view('admin.evaluations.show', [
            'evaluation' => $evaluation,
            'criteria' => $evaluation->category->activeCriteria()->get(),
            'history' => $this->corrections->historyFor($evaluation),
            'release' => $evaluation->category->resultRelease()->first(),
        ]);
    }

    /**
     * Correct the scores and the feedback of any evaluation.
     *
     * The original judge keeps the evaluation: an administrator never
     * impersonates the judge and no duplicate evaluation is created.
     */
    public function correct(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'integer', 'min:'.\App\Services\ScoringService::MIN_SCORE, 'max:'.\App\Services\ScoringService::MAX_SCORE],
            'comment' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_released_results' => ['nullable', 'boolean'],
        ]);

        if (! $this->releasedResultsAcknowledged($evaluation, $request)) {
            return back()->with('error', 'The official result of this category was already released. Confirm the results correction workflow before you change this evaluation.');
        }

        try {
            $this->corrections->correct(
                actor: $request->user(),
                evaluation: $evaluation,
                scores: $data['scores'],
                comment: $data['comment'] ?? null,
                reason: $data['reason'],
                actorRole: EvaluationCorrectionService::ACTOR_ADMIN,
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', 'The correction could not be saved. Every criterion needs a score between 1 and 5.');
        }

        return back()->with('status', 'The correction has been saved and recorded in the audit log.');
    }

    /**
     * Clear the scores and the feedback of an evaluation. The evaluation
     * returns to the draft state.
     */
    public function resetScores(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_released_results' => ['nullable', 'boolean'],
        ]);

        if (! $this->releasedResultsAcknowledged($evaluation, $request)) {
            return back()->with('error', 'The official result of this category was already released. Confirm the results correction workflow before you reset this evaluation.');
        }

        $this->corrections->reset(
            actor: $request->user(),
            evaluation: $evaluation,
            actionType: AuditLog::ACTION_RESET_SCORES,
            reason: $data['reason'],
            actorRole: EvaluationCorrectionService::ACTOR_ADMIN,
        );

        return back()->with('status', 'The scores and the feedback have been reset. The evaluation is a draft again.');
    }

    /**
     * Restore the initial "not started" state of an evaluation.
     *
     * The evaluation record and its relationships are preserved; the history
     * stays in the audit log.
     */
    public function resetSubmission(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_released_results' => ['nullable', 'boolean'],
        ]);

        if (! $this->releasedResultsAcknowledged($evaluation, $request)) {
            return back()->with('error', 'The official result of this category was already released. Confirm the results correction workflow before you reset this evaluation.');
        }

        $this->corrections->reset(
            actor: $request->user(),
            evaluation: $evaluation,
            actionType: AuditLog::ACTION_RESET_SUBMISSION,
            reason: $data['reason'],
            actorRole: EvaluationCorrectionService::ACTOR_ADMIN,
        );

        return back()->with('status', 'The evaluation has been reset to "not started" and no longer counts as submitted.');
    }

    /**
     * Reopen a submitted evaluation for a judge. Requires an explicit reason
     * and is always written to the audit log.
     */
    public function reopen(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        DB::transaction(function () use ($evaluation) {
            $evaluation->update([
                'status' => Evaluation::STATUS_DRAFT,
                'submitted_at' => null,
                'reopen_count' => $evaluation->reopen_count + 1,
            ]);
        });

        $this->audit->log(
            'evaluation.reopen',
            'Reopened evaluation #'.$evaluation->id.' ('.$evaluation->judge->name.' / '.$evaluation->finalist->code.'): '.$data['reason'],
            Evaluation::class,
            $evaluation->id,
            ['reason' => $data['reason'], 'judge_id' => $evaluation->judge_id, 'finalist_id' => $evaluation->finalist_id]
        );

        return back()->with('status', 'Evaluation reopened. The judge may edit it again.');
    }

    public function lock(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $evaluation->update(['status' => Evaluation::STATUS_LOCKED]);

        $this->audit->log(
            'evaluation.lock',
            'Locked evaluation #'.$evaluation->id.' ('.$evaluation->judge->name.' / '.$evaluation->finalist->code.'): '.$data['reason'],
            Evaluation::class,
            $evaluation->id,
            ['reason' => $data['reason']]
        );

        return back()->with('status', 'Evaluation locked.');
    }

    /**
     * A correction of a released category has to be acknowledged explicitly.
     */
    private function releasedResultsAcknowledged(Evaluation $evaluation, Request $request): bool
    {
        if (! $evaluation->category->resultRelease()->exists()) {
            return true;
        }

        return $request->boolean('confirm_released_results');
    }
}
