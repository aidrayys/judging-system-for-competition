<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompetitionSetting;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Services\ProvisionalRankingService;
use App\Support\DateTimeDisplay;

/**
 * Covers the evaluation management workflow:
 *  - a judge corrects their own submitted evaluation while the period is open
 *  - a judge can never touch the evaluation of another judge
 *  - a closed judging period blocks every write on the server
 *  - an administrator corrects and resets any evaluation with a reason
 *  - a reset clears the scores and removes the evaluation from the counts
 *  - every correction and reset is written to the audit log with the previous
 *    and the new values
 *  - the weighted total and the ranking eligibility follow the correction
 *  - a released result is flagged for review when it is affected
 *  - every timestamp is displayed in the display timezone (WIB when configured)
 */
class EvaluationCorrectionTest extends CompetitionTestCase
{
    /**
     * @return array<int, int> criterion id => score
     */
    private function scoresFor(int $score): array
    {
        return $this->category->activeCriteria()->pluck('id')->mapWithKeys(fn ($id) => [$id => $score])->all();
    }

    private function submitFor(User $judge, Finalist $finalist, int $score = 4): Evaluation
    {
        $category = $finalist->category;
        $scores = $category->activeCriteria()->pluck('id')->mapWithKeys(fn ($id) => [$id => $score])->all();

        $this->actingAs($judge)->postJson("/judge/category/{$category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $scores,
        ])->assertOk();

        $this->actingAs($judge)
            ->post("/judge/category/{$category->id}/finalist/{$finalist->id}/submit")
            ->assertRedirect();

        return Evaluation::where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();
    }

    private function closeJudging(): void
    {
        CompetitionSetting::put('judging_open', '0');
    }

    public function test_a_judge_can_correct_their_own_submitted_evaluation_while_the_period_is_open(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        \Illuminate\Support\Carbon::setTestNow('2026-10-22 03:00:00');
        $evaluation = $this->submitFor($judge, $finalist, 4);
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);
        $originalSubmittedAt = $evaluation->submitted_at->copy();

        \Illuminate\Support\Carbon::setTestNow('2026-10-22 03:05:00');

        $this->actingAs($judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/correct", [
                'scores' => $this->scoresFor(5),
                'comment' => 'Corrected after a discussion with the finalist.',
                'reason' => 'I mixed up two criteria.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        \Illuminate\Support\Carbon::setTestNow();

        $evaluation = $evaluation->fresh();

        $this->assertSame(Evaluation::STATUS_SUBMITTED, $evaluation->status, 'a correction keeps the submitted status');
        $this->assertEqualsWithDelta(100.0, (float) $evaluation->total_score, 0.001);
        $this->assertSame('Corrected after a discussion with the finalist.', $evaluation->comment);
        $this->assertCount(6, $evaluation->scores);
        $this->assertTrue($evaluation->submitted_at->greaterThan($originalSubmittedAt), 'the saved timestamp is updated');
    }

    public function test_a_judge_can_never_touch_the_evaluation_of_another_judge(): void
    {
        $judgeA = $this->designatedJudges['judge_a'];
        $judgeD = $this->designatedJudges['judge_d'];
        $finalist = $this->makeFinalist();

        $judgeAEvaluation = $this->submitFor($judgeA, $finalist, 4);
        $judgeDEvaluation = $this->submitFor($judgeD, $finalist, 3);

        // Judge A corrects the evaluation - the route never accepts an
        // evaluation id, so Judge D's evaluation cannot be addressed.
        $this->actingAs($judgeA)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/correct", [
                'scores' => $this->scoresFor(5),
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(100.0, (float) $judgeAEvaluation->fresh()->total_score, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $judgeDEvaluation->fresh()->total_score, 0.001, 'another judge is never affected');
        $this->assertSame($judgeD->id, $judgeDEvaluation->fresh()->judge_id, 'the evaluator identity is preserved');
    }

    public function test_a_judge_cannot_correct_or_reset_while_the_period_is_closed(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);
        $this->closeJudging();

        $this->actingAs($judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/correct", [
                'scores' => $this->scoresFor(5),
            ])
            ->assertForbidden();

        $this->actingAs($judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/reset", [
                'reason' => 'Closed period.',
            ])
            ->assertForbidden();

        $evaluation = $evaluation->fresh();
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $evaluation->status);
    }

    public function test_an_admin_can_correct_any_evaluation_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);
        $evaluationsBefore = Evaluation::count();

        $this->actingAs($admin)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(4),
                'comment' => 'Corrected by the administrator.',
                'reason' => 'The judge reported a wrong score.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $evaluation = $evaluation->fresh();

        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $evaluation->status);
        $this->assertSame($judge->id, $evaluation->judge_id, 'the original judge keeps the evaluation');
        $this->assertSame($evaluationsBefore, Evaluation::count(), 'no duplicate evaluation is created');
    }

    public function test_an_admin_correction_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $this->actingAs($admin)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(4),
                'reason' => 'short',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertEqualsWithDelta(80.0, (float) $evaluation->fresh()->total_score, 0.001);
    }

    public function test_a_reset_clears_the_scores_and_removes_the_evaluation_from_the_submitted_count(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $this->actingAs($admin)
            ->post("/admin/evaluations/{$evaluation->id}/reset-scores", [
                'reason' => 'The evaluation was scored by the wrong account.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $evaluation = $evaluation->fresh();

        $this->assertCount(0, $evaluation->scores, 'all six scores are cleared');
        $this->assertNull($evaluation->total_score, 'the weighted total is cleared');
        $this->assertNull($evaluation->comment, 'the feedback is cleared');
        $this->assertSame(Evaluation::STATUS_DRAFT, $evaluation->status);
        $this->assertNull($evaluation->submitted_at);
        $this->assertSame(Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count(), 0);
    }

    public function test_a_reset_submission_restores_the_not_started_state_and_keeps_the_record(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);
        $evaluationsBefore = Evaluation::count();

        $this->actingAs($admin)
            ->post("/admin/evaluations/{$evaluation->id}/reset-submission", [
                'reason' => 'The submission has to start over completely.',
            ])
            ->assertRedirect();

        $evaluation = $evaluation->fresh();

        $this->assertSame(Evaluation::STATUS_DRAFT, $evaluation->status);
        $this->assertNull($evaluation->submitted_at);
        $this->assertNull($evaluation->total_score);
        $this->assertCount(0, $evaluation->scores);
        $this->assertSame(0, $evaluation->reopen_count);
        $this->assertSame($evaluationsBefore, Evaluation::count(), 'the evaluation record is preserved');
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id]);
    }

    public function test_a_judge_can_reset_their_own_evaluation(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $this->actingAs($judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/reset", [
                'reason' => 'I want to score it again.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $evaluation = $evaluation->fresh();

        $this->assertCount(0, $evaluation->scores);
        $this->assertSame(Evaluation::STATUS_DRAFT, $evaluation->status);
        $this->assertNull($evaluation->total_score);
        $this->assertSame(0, Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count());
    }

    public function test_the_audit_history_preserves_the_previous_values(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $this->actingAs($admin)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(5),
                'comment' => 'Updated feedback.',
                'reason' => 'The judge reported a wrong score.',
            ])
            ->assertRedirect();

        $entry = AuditLog::where('evaluation_id', $evaluation->id)
            ->where('action_type', AuditLog::ACTION_SCORE_CORRECTION)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'the correction is written to the audit log');
        $this->assertSame(AuditLog::ACTION_SCORE_CORRECTION, $entry->action_type);
        $this->assertSame('admin', $entry->actor_role);
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame($judge->id, $entry->original_judge_id, 'the original judge is kept');
        $this->assertEqualsWithDelta(80.0, (float) $entry->previous_total, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $entry->new_total, 0.001);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $entry->previous_status);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $entry->new_status);
        $this->assertCount(6, $entry->previous_scores);
        $this->assertCount(6, $entry->new_scores);
        $this->assertSame('The judge reported a wrong score.', $entry->reason);

        // The history is visible for the evaluation.
        $this->actingAs($admin)->get("/admin/evaluations/{$evaluation->id}")
            ->assertOk()
            ->assertSee('Correction history')
            ->assertSee('Score Correction');
    }

    public function test_a_reset_is_recorded_in_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $this->actingAs($admin)
            ->post("/admin/evaluations/{$evaluation->id}/reset-scores", [
                'reason' => 'Scored by the wrong account.',
            ])
            ->assertRedirect();

        $entry = AuditLog::where('evaluation_id', $evaluation->id)
            ->where('action_type', AuditLog::ACTION_RESET_SCORES)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('admin', $entry->actor_role);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $entry->previous_status);
        $this->assertSame(Evaluation::STATUS_DRAFT, $entry->new_status);
        $this->assertEqualsWithDelta(80.0, (float) $entry->previous_total, 0.001);
        $this->assertNull($entry->new_total);
        $this->assertSame('Scored by the wrong account.', $entry->reason);
    }

    public function test_the_weighted_total_and_the_ranking_eligibility_follow_a_correction(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $ranking = app(ProvisionalRankingService::class)->forCategory($this->category->fresh());
        $this->assertEqualsWithDelta(80.0, $ranking['rows'][0]['average'], 0.001);
        $this->assertSame(1, $ranking['rows'][0]['submitted_count']);
        $this->assertSame('in_progress', $ranking['rows'][0]['status']);

        $this->actingAs($admin)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(5),
                'reason' => 'The judge reported a wrong score.',
            ])
            ->assertRedirect();

        $ranking = app(ProvisionalRankingService::class)->forCategory($this->category->fresh());
        $this->assertEqualsWithDelta(100.0, $ranking['rows'][0]['average'], 0.001);

        // A reset removes the evaluation from the submitted count again.
        $this->actingAs($admin)
            ->post("/admin/evaluations/{$evaluation->id}/reset-scores", [
                'reason' => 'The scores have to be entered again.',
            ])
            ->assertRedirect();

        $ranking = app(ProvisionalRankingService::class)->forCategory($this->category->fresh());
        $this->assertSame(0, $ranking['rows'][0]['submitted_count']);
        $this->assertNull($ranking['rows'][0]['average'], 'a reset evaluation has no score');
        $this->assertSame(0, $ranking['submitted']);
    }

    public function test_a_correction_of_a_released_result_flags_the_category_for_review(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        // Every designated judge submits, so the category is complete and can
        // be released.
        foreach ($this->designatedJudges as $designated) {
            $this->submitFor($designated, $finalist, 4);
        }

        $evaluation = Evaluation::where('judge_id', $judge->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        $this->actingAs($admin)->post("/admin/results/{$this->category->id}/release")->assertRedirect();
        $this->assertTrue($this->category->fresh()->resultRelease()->first()->needsReview() === false);

        // The correction has to acknowledge the released result.
        $this->actingAs($admin)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(5),
                'reason' => 'The judge reported a wrong score.',
            ])
            ->assertSessionHas('error');

        $this->assertEqualsWithDelta(80.0, (float) $evaluation->fresh()->total_score, 0.001, 'the change is refused');

        // With the acknowledgement the correction is applied and the result
        // is flagged for review.
        $this->actingAs($admin)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(5),
                'reason' => 'The judge reported a wrong score.',
                'confirm_released_results' => 1,
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(100.0, (float) $evaluation->fresh()->total_score, 0.001);
        $this->assertTrue($this->category->fresh()->resultRelease()->first()->needsReview());

        $this->actingAs($admin)->get("/admin/results/{$this->category->id}")
            ->assertOk()
            ->assertSee('has to be reviewed');

        // The administrator confirms the review and republishes the result.
        $this->actingAs($admin)
            ->post("/admin/results/{$this->category->id}/republish", [
                'note' => 'The correction was reviewed and confirmed.',
            ])
            ->assertRedirect();

        $this->assertFalse($this->category->fresh()->resultRelease()->first()->needsReview());
    }

    public function test_unauthorized_requests_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        // A judge may not use the administrator workflow.
        $this->actingAs($judge)
            ->put("/admin/evaluations/{$evaluation->id}", [
                'scores' => $this->scoresFor(5),
                'reason' => 'I want to change the score.',
            ])
            ->assertForbidden();

        $this->actingAs($judge)
            ->post("/admin/evaluations/{$evaluation->id}/reset-scores", [
                'reason' => 'I want to reset the score.',
            ])
            ->assertForbidden();

        $this->actingAs($judge)
            ->post("/admin/evaluations/{$evaluation->id}/reset-submission", [
                'reason' => 'I want to reset the submission.',
            ])
            ->assertForbidden();

        $this->assertEqualsWithDelta(80.0, (float) $evaluation->fresh()->total_score, 0.001);
    }

    public function test_a_guest_is_redirected_to_the_login(): void
    {
        $finalist = $this->makeFinalist();

        $this->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/correct", [
            'scores' => $this->scoresFor(5),
        ])->assertRedirect(route('login'));

        $this->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/reset", [
            'reason' => 'I want to reset the evaluation.',
        ])->assertRedirect(route('login'));
    }

    public function test_timestamps_are_displayed_in_wib(): void
    {
        $admin = User::factory()->admin()->create();

        config(['app.display_timezone' => 'Asia/Jakarta']);
        $this->assertSame('UTC', config('app.timezone'), 'the storage timezone stays UTC');

        // A timestamp that crosses midnight when it is converted.
        $utc = \Illuminate\Support\Carbon::parse('2026-10-21 18:30:00', 'UTC');
        $formatted = DateTimeDisplay::format($utc);

        $this->assertStringContainsString('22 Oct 2026', $formatted);
        $this->assertStringContainsString('01:30:00', $formatted);
        $this->assertStringEndsWith('WIB', $formatted);

        // The admin audit log page has been removed.
        $this->actingAs($admin)->get('/admin/audit-logs')->assertNotFound();
    }

    public function test_the_judge_page_offers_the_correction_actions(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $this->submitFor($judge, $finalist, 4);

        $this->actingAs($judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk()
            ->assertSee('Edit My Scores')
            ->assertSee('Save Corrections')
            ->assertSee('Cancel')
            ->assertSee('Reset My Scores');
    }

    public function test_the_judge_interface_has_no_reason_field_and_the_documented_dialogs(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $this->submitFor($judge, $finalist, 4);

        $response = $this->actingAs($judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk();

        $html = $response->getContent();
        // The dialog texts are compared without the line breaks of the markup.
        $text = preg_replace('/\s+/', ' ', $html);

        // The judge does not have to enter a reason any more.
        $this->assertStringNotContainsString('name="reason"', $html, 'the judge interface must not ask for a reason');
        $this->assertStringNotContainsString('correction-reason', $html);
        $this->assertStringNotContainsString('reset-reason', $html);

        // The three confirmation dialogs with their documented wording.
        $this->assertStringContainsString(
            'Are you sure you want to correct your submitted evaluation? You can change your scores and submit the corrections again.',
            $text
        );
        $this->assertStringContainsString('Yes, Edit Scores', $text);
        $this->assertStringContainsString('Save Score Corrections?', $text);
        $this->assertStringContainsString(
            'Are you sure you want to save these changes? The updated scores will replace the previous scores, and the correction will be recorded in the audit log.',
            $text
        );
        $this->assertStringContainsString('Yes, Save Corrections', $text);
        $this->assertStringContainsString('Reset Evaluation?', $text);
        $this->assertStringContainsString(
            'Are you sure you want to reset this evaluation? All six criterion scores and feedback will be cleared. The evaluation status will return to Not Started, and you will need to evaluate all criteria and submit again. The historical audit record will be preserved.',
            $text
        );
        $this->assertStringContainsString('Yes, Reset', $text);
    }

    public function test_a_judge_correction_needs_no_reason_and_answers_with_json(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $response = $this->actingAs($judge)
            ->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/correct", [
                'scores' => $this->scoresFor(5),
                'comment' => 'Corrected without a reason.',
            ])
            ->assertOk();

        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', Evaluation::STATUS_SUBMITTED);
        $this->assertEqualsWithDelta(100.0, (float) $response->json('total_score'), 0.001);
        $this->assertNotNull($response->json('saved_at'));

        $evaluation = $evaluation->fresh();
        $this->assertEqualsWithDelta(100.0, (float) $evaluation->total_score, 0.001);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $evaluation->status);

        // The correction is recorded without a reason.
        $entry = AuditLog::where('evaluation_id', $evaluation->id)
            ->where('action_type', AuditLog::ACTION_SCORE_CORRECTION)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->reason);
        $this->assertSame('judge', $entry->actor_role);
    }

    public function test_a_failing_judge_correction_answers_with_the_validation_errors(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        // One criterion is missing.
        $scores = $this->scoresFor(5);
        unset($scores[array_key_first($scores)]);

        $this->actingAs($judge)
            ->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/correct", [
                'scores' => $scores,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertEqualsWithDelta(80.0, (float) $evaluation->fresh()->total_score, 0.001, 'nothing is saved');
    }

    public function test_a_judge_reset_needs_no_reason_and_keeps_the_history(): void
    {
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        $evaluation = $this->submitFor($judge, $finalist, 4);

        $this->actingAs($judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/reset")
            ->assertRedirect()
            ->assertSessionHas('status');

        $evaluation = $evaluation->fresh();

        $this->assertCount(0, $evaluation->scores, 'all six scores are cleared');
        $this->assertNull($evaluation->total_score, 'the weighted total is cleared');
        $this->assertNull($evaluation->comment);
        $this->assertSame(Evaluation::STATUS_DRAFT, $evaluation->status, 'the evaluation starts over');
        $this->assertNull($evaluation->submitted_at);
        $this->assertSame(0, Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count());

        // The reset is recorded automatically, without a reason.
        $entry = AuditLog::where('evaluation_id', $evaluation->id)
            ->where('action_type', AuditLog::ACTION_RESET_SCORES)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'the reset is written to the audit log');
        $this->assertNull($entry->reason, 'a judge does not have to enter a reason');
        $this->assertSame('judge', $entry->actor_role);
        $this->assertSame($judge->id, $entry->user_id);
        $this->assertSame($judge->id, $entry->original_judge_id);
        $this->assertEqualsWithDelta(80.0, (float) $entry->previous_total, 0.001, 'the previous values are preserved');
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $entry->previous_status);
        $this->assertSame(Evaluation::STATUS_DRAFT, $entry->new_status);

        // The evaluation record itself is never deleted.
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id]);
    }

    public function test_a_judge_cannot_reset_an_evaluation_after_the_results_were_released(): void
    {
        $admin = User::factory()->admin()->create();
        $judge = $this->designatedJudges['judge_a'];
        $finalist = $this->makeFinalist();

        foreach ($this->designatedJudges as $designated) {
            $this->submitFor($designated, $finalist, 4);
        }

        $this->actingAs($admin)->post("/admin/results/{$this->category->id}/release")->assertRedirect();

        $this->actingAs($judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/reset")
            ->assertSessionHas('error');

        $this->assertSame(
            5,
            Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count(),
            'the released evaluations are not reset by a judge'
        );
    }
}
