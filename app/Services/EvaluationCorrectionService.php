<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrections and resets of existing evaluations.
 *
 * The service is the only place where an already submitted evaluation may be
 * changed. It guarantees that:
 *  - the weighted total is recalculated by the server (never by the browser)
 *  - the evaluator identity is preserved (an admin never impersonates a judge
 *    and no duplicate evaluation row is created)
 *  - the evaluation and its audit entry are written in one transaction
 *  - every correction and reset is recorded with the previous and the new
 *    values, so the history is never overwritten
 *  - an officially released result is flagged for administrator review when
 *    it is affected by the change
 */
class EvaluationCorrectionService
{
    public const ACTOR_JUDGE = 'judge';

    public const ACTOR_ADMIN = 'admin';

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Correct the scores and the feedback of an existing evaluation.
     *
     * A corrected evaluation keeps its "submitted" status; only the stored
     * values and the saved timestamp change.
     *
     * @param  array<int|string, mixed>  $scores  criterion id => score
     */
    public function correct(User $actor, Evaluation $evaluation, array $scores, ?string $comment, ?string $reason, string $actorRole): Evaluation
    {
        $criteria = $evaluation->category->activeCriteria()->get();
        $cleanScores = $this->validateScores($criteria, $scores);

        return DB::transaction(function () use ($actor, $evaluation, $criteria, $cleanScores, $comment, $reason, $actorRole) {
            $evaluation->load('scores');

            $previousScores = $evaluation->scores->pluck('score', 'criterion_id')->all();
            $previousTotal = $evaluation->total_score !== null ? (float) $evaluation->total_score : null;
            $previousStatus = $evaluation->status;
            $previousComment = $evaluation->comment;

            foreach ($cleanScores as $criterionId => $score) {
                $evaluation->scores()->updateOrCreate(
                    ['criterion_id' => $criterionId],
                    ['score' => $score]
                );
            }

            $total = $this->scoring->weightedTotal($criteria, $cleanScores);

            $evaluation->comment = $comment;
            $evaluation->total_score = $total;

            // A correction keeps the submitted state; the saved timestamp
            // reflects the moment of the correction.
            if ($evaluation->isSubmitted()) {
                $evaluation->submitted_at = now();
            }

            $evaluation->save();

            $this->writeAudit(
                actor: $actor,
                evaluation: $evaluation,
                actionType: AuditLog::ACTION_SCORE_CORRECTION,
                actorRole: $actorRole,
                previousScores: $previousScores,
                newScores: $cleanScores,
                previousTotal: $previousTotal,
                newTotal: $total,
                previousStatus: $previousStatus,
                newStatus: $evaluation->status,
                previousComment: $previousComment,
                newComment: $evaluation->comment,
                reason: $reason,
            );

            $this->flagReleasedResult($evaluation);

            return $evaluation->fresh();
        });
    }

    /**
     * Reset an evaluation.
     *
     * - "reset_scores" clears the six scores and the feedback and returns the
     *   evaluation to the draft state.
     * - "reset_submission" additionally restores the initial "not started"
     *   state: no scores, no feedback, no submission timestamp.
     *
     * The evaluation row itself and its relationships are always preserved;
     * the history stays in the audit log.
     */
    public function reset(User $actor, Evaluation $evaluation, string $actionType, ?string $reason, string $actorRole): Evaluation
    {
        if (! in_array($actionType, [AuditLog::ACTION_RESET_SCORES, AuditLog::ACTION_RESET_SUBMISSION], true)) {
            throw ValidationException::withMessages([
                'action' => 'Unknown reset action.',
            ]);
        }

        return DB::transaction(function () use ($actor, $evaluation, $actionType, $reason, $actorRole) {
            $evaluation->load('scores');

            $previousScores = $evaluation->scores->pluck('score', 'criterion_id')->all();
            $previousTotal = $evaluation->total_score !== null ? (float) $evaluation->total_score : null;
            $previousStatus = $evaluation->status;
            $previousComment = $evaluation->comment;

            // Clear the scores and the feedback. The record itself stays.
            $evaluation->scores()->delete();
            $evaluation->comment = null;
            $evaluation->total_score = null;
            $evaluation->status = Evaluation::STATUS_DRAFT;
            $evaluation->submitted_at = null;
            $evaluation->reopen_count = $actionType === AuditLog::ACTION_RESET_SUBMISSION
                ? 0
                : $evaluation->reopen_count + 1;
            $evaluation->save();

            $this->writeAudit(
                actor: $actor,
                evaluation: $evaluation,
                actionType: $actionType,
                actorRole: $actorRole,
                previousScores: $previousScores,
                newScores: [],
                previousTotal: $previousTotal,
                newTotal: null,
                previousStatus: $previousStatus,
                newStatus: $evaluation->status,
                previousComment: $previousComment,
                newComment: null,
                reason: $reason,
            );

            $this->flagReleasedResult($evaluation);

            return $evaluation->fresh();
        });
    }

    /**
     * Validate the submitted scores against the active criteria.
     *
     * Every active criterion needs an integer score between 1 and 5. An
     * unselected criterion is never treated as zero.
     *
     * @param  array<int|string, mixed>  $scores
     * @return array<int, int> criterion id => score
     */
    public function validateScores(Collection $criteria, array $scores): array
    {
        $clean = [];
        $errors = [];

        foreach ($criteria as $criterion) {
            $value = $scores[$criterion->id] ?? $scores[(string) $criterion->id] ?? null;

            if ($value === null || $value === '' || ! $this->scoring->isValidScore($value)) {
                $errors[$criterion->name] = 'A score between 1 and 5 is required for "'.$criterion->name.'".';

                continue;
            }

            $clean[$criterion->id] = (int) $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * The correction history of a single evaluation.
     *
     * @return Collection<int, AuditLog>
     */
    public function historyFor(Evaluation $evaluation): Collection
    {
        return AuditLog::query()
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('action_type', [
                AuditLog::ACTION_SCORE_CORRECTION,
                AuditLog::ACTION_RESET_SCORES,
                AuditLog::ACTION_RESET_SUBMISSION,
            ])
            ->with(['user', 'originalJudge'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Write the durable audit entry of a correction or a reset.
     */
    private function writeAudit(
        User $actor,
        Evaluation $evaluation,
        string $actionType,
        string $actorRole,
        array $previousScores,
        array $newScores,
        ?float $previousTotal,
        ?float $newTotal,
        ?string $previousStatus,
        ?string $newStatus,
        ?string $previousComment,
        ?string $newComment,
        ?string $reason,
    ): AuditLog {
        $label = match ($actionType) {
            AuditLog::ACTION_SCORE_CORRECTION => 'Score Correction',
            AuditLog::ACTION_RESET_SCORES => 'Reset Scores',
            AuditLog::ACTION_RESET_SUBMISSION => 'Reset Submission',
            default => $actionType,
        };

        $description = sprintf(
            '%s on %s (%s / %s) by %s (%s)',
            $label,
            $evaluation->finalist->code,
            $evaluation->category->name,
            $evaluation->judge->name,
            $actor->name,
            $actorRole
        );

        return $this->audit->log(
            'evaluation.'.str_replace('score_correction', 'correction', $actionType),
            $description,
            Evaluation::class,
            $evaluation->id,
            [
                'action_type' => $actionType,
                'actor_role' => $actorRole,
                'finalist_id' => $evaluation->finalist_id,
                'category_id' => $evaluation->category_id,
                'original_judge_id' => $evaluation->judge_id,
                'previous_scores' => $previousScores,
                'new_scores' => $newScores,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            [
                'evaluation_id' => $evaluation->id,
                'action_type' => $actionType,
                'actor_role' => $actorRole,
                'original_judge_id' => $evaluation->judge_id,
                'previous_scores' => $previousScores,
                'new_scores' => $newScores,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'previous_comment' => $previousComment,
                'new_comment' => $newComment,
                'reason' => $reason,
            ],
        );
    }

    /**
     * Flag an officially released result when it is affected by a change.
     */
    private function flagReleasedResult(Evaluation $evaluation): void
    {
        $release = $evaluation->category->resultRelease()->first();

        if (! $release) {
            return;
        }

        $release->update([
            'review_required' => true,
            'reviewed_at' => null,
        ]);

        $this->audit->log(
            'results.review_required',
            'The official result of '.$evaluation->category->name.' is affected by a correction and has to be reviewed and republished.',
            \App\Models\Category::class,
            $evaluation->category_id,
            [
                'evaluation_id' => $evaluation->id,
                'released_at' => (string) $release->released_at,
            ],
        );
    }
}
