<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSetting;
use App\Models\Evaluation;
use App\Models\User;

/**
 * Covers the revised evaluation workflow:
 *  - successful autosave and database persistence
 *  - handling of failed autosave requests
 *  - submission blocked when a criterion is incomplete
 *  - submission blocked when the judging period is closed
 *  - submission succeeds when all six valid scores are saved and the period is open
 *  - invalid scores are rejected
 *  - a submitted evaluation is locked against further edits
 */
class AutosaveSubmissionTest extends CompetitionTestCase
{
    private function autosaveUrl($finalist): string
    {
        return "/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave";
    }

    private function submitUrl($finalist): string
    {
        return "/judge/category/{$this->category->id}/finalist/{$finalist->id}/submit";
    }

    public function test_autosave_persists_the_scores_to_the_database(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores, 'comment' => 'Persisted draft'])
            ->assertOk();

        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('complete', true);
        $response->assertJsonPath('scored_count', 6);
        $response->assertJsonPath('total_score', 80);
        $response->assertJsonPath('preview_total', 80);

        $evaluation = Evaluation::first();
        $this->assertNotNull($evaluation);
        $this->assertSame('draft', $evaluation->status);
        $this->assertSame('Persisted draft', $evaluation->comment);
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);
        $this->assertDatabaseCount('evaluation_scores', 6);
    }

    public function test_autosave_returns_the_persisted_contributions_for_every_criterion(): void
    {
        $finalist = $this->makeFinalist();
        $scores = [];
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $scores[$criterion->id] = $criterion->weight <= 15 ? 5 : 3;
        }

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk();

        $contributions = $response->json('contributions');
        $expectedTotal = 0.0;

        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $expected = round(($scores[$criterion->id] / 5) * (float) $criterion->weight, 2);
            $expectedTotal += $expected;

            $this->assertEqualsWithDelta($expected, (float) $contributions[$criterion->id], 0.001);
        }

        $this->assertEqualsWithDelta(round($expectedTotal, 2), (float) $response->json('preview_total'), 0.001);
    }

    public function test_a_failed_autosave_keeps_the_draft_untouched(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(3)])
            ->assertOk();

        $before = Evaluation::first()->total_score;

        // The judging period is closed -> the server rejects the request.
        CompetitionSetting::put('judging_open', '0');

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(2)])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('read_only', true);

        $evaluation = Evaluation::first()->refresh();
        $this->assertEqualsWithDelta((float) $before, (float) $evaluation->total_score, 0.001);
        $this->assertSame('draft', $evaluation->status);
    }

    public function test_an_invalid_score_is_rejected_and_nothing_is_persisted(): void
    {
        $finalist = $this->makeFinalist();

        foreach ([0, 11, 13] as $invalid) {
            $scores = $this->fullScores(4);
            $scores[array_key_first($scores)] = $invalid;

            $this->actingAs($this->judge)
                ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
                ->assertStatus(422);
        }

        $this->assertDatabaseCount('evaluation_scores', 0);
    }

    public function test_submission_is_blocked_when_a_criterion_is_incomplete(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);
        $scores[array_key_first($scores)] = null;

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk()
            ->assertJsonPath('complete', false)
            ->assertJsonPath('scored_count', 5);

        $this->actingAs($this->judge)
            ->from($this->submitUrl($finalist))
            ->post($this->submitUrl($finalist))
            ->assertRedirect()
            ->assertSessionHas('error');

        $evaluation = Evaluation::first();
        $this->assertSame('draft', $evaluation->status);
        $this->assertNull($evaluation->submitted_at);
    }

    public function test_submission_is_blocked_when_the_judging_period_is_closed(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        CompetitionSetting::put('judging_open', '0');

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertForbidden();

        $evaluation = Evaluation::first();
        $this->assertSame('draft', $evaluation->status);
        $this->assertNull($evaluation->submitted_at);
    }

    public function test_submission_succeeds_when_all_six_scores_are_saved_and_the_period_is_open(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertRedirect("/judge/category/{$this->category->id}/finalist/{$finalist->id}/submitted");

        $evaluation = Evaluation::first();
        $this->assertSame('submitted', $evaluation->status);
        $this->assertNotNull($evaluation->submitted_at);
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);
    }

    public function test_a_submitted_evaluation_is_locked_against_further_edits(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $this->actingAs($this->judge)->post($this->submitUrl($finalist))->assertRedirect();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(1)])
            ->assertStatus(409)
            ->assertJsonPath('read_only', true);

        $this->assertEqualsWithDelta(80.0, (float) Evaluation::first()->total_score, 0.001);
    }

    public function test_only_the_owning_judge_can_write_an_evaluation(): void
    {
        $other = User::factory()->judge()->create(['username' => 'somebody-else']);
        $finalist = $this->makeFinalist();

        $this->actingAs($other)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(5)])
            ->assertOk();

        // The other judge has their own evaluation, this judge has none.
        $this->assertDatabaseHas('evaluations', ['judge_id' => $other->id, 'finalist_id' => $finalist->id]);
        $this->assertDatabaseMissing('evaluations', ['judge_id' => $this->judge->id]);
    }

    public function test_an_evaluation_cannot_be_created_for_another_category(): void
    {
        $otherCategory = Category::factory()->create(['slug' => 'feasibility-study']);
        $this->seedRubric($otherCategory);
        $finalist = $this->makeFinalist($otherCategory);

        // The finalist belongs to the other category -> 404.
        $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertNotFound();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(5)])
            ->assertNotFound();
    }

    public function test_the_evaluation_page_exposes_the_weights_to_the_frontend(): void
    {
        $finalist = $this->makeFinalist();

        $response = $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk();

        // The scoring module needs the exact weights of every criterion.
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $response->assertSee('"weight":'.(float) $criterion->weight, false);
        }
    }

    public function test_the_submission_uses_a_custom_modal_instead_of_a_native_confirm(): void
    {
        $finalist = $this->makeFinalist();

        $html = $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk()
            ->getContent();

        // The browser native confirmation is gone ...
        $this->assertStringNotContainsString('return confirm(', $html);
        $this->assertStringNotContainsString('onsubmit="return confirm', $html);

        // ... and the custom modal is rendered instead.
        $text = preg_replace('/\s+/', ' ', $html);
        $this->assertStringContainsString('Submit Evaluation?', $text);
        $this->assertStringContainsString(
            'Are you sure you want to submit this evaluation? Please make sure all six criteria and your feedback are correct before continuing.',
            $text
        );
        $this->assertStringContainsString('Yes, Submit', $text);
        $this->assertStringContainsString('submit-confirm-dialog', $html);
    }

    public function test_a_submission_is_stored_once_and_a_second_attempt_is_refused(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertRedirect("/judge/category/{$this->category->id}/finalist/{$finalist->id}/submitted");

        $evaluation = Evaluation::first();
        $this->assertSame('submitted', $evaluation->status);
        $this->assertNotNull($evaluation->submitted_at);
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);

        // A second submission does not create another evaluation record.
        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Evaluation::where('finalist_id', $finalist->id)->count());
        $this->assertSame('submitted', $evaluation->fresh()->status);
    }
}
