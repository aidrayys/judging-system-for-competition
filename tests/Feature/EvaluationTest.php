<?php

namespace Tests\Feature;

use App\Models\CompetitionSetting;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EvaluationTest extends CompetitionTestCase
{
    public function test_judge_dashboard_lists_finalists(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->get('/judge')
            ->assertOk()
            ->assertSee($finalist->name)
            ->assertSee('Not Started');
    }

    public function test_judge_sees_only_their_own_evaluation(): void
    {
        $other = User::factory()->judge()->create(['username' => 'other-judge']);
        $finalist = $this->makeFinalist();

        // The other judge submits an evaluation.
        $this->actingAs($other)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(5),
            'comment' => 'Secret comment of another judge',
        ])->assertOk();

        $response = $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk();

        $response->assertDontSee('Secret comment of another judge');

        // The other judge's score must not be pre-selected for this judge.
        $criterionId = $this->category->activeCriteria()->first()->id;
        $this->assertDoesNotMatchRegularExpression(
            '/id="score-'.$criterionId.'-5"[^>]*\schecked/',
            $response->getContent()
        );

        // This judge has no evaluation of their own yet.
        $this->assertDatabaseMissing('evaluations', ['judge_id' => $this->judge->id]);
    }

    public function test_autosave_persists_scores_and_creates_a_single_evaluation(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);

        $response = $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $scores,
            'comment' => 'Good proposal',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseCount('evaluations', 1);
        $this->assertDatabaseCount('evaluation_scores', 6);

        $evaluation = Evaluation::first();
        $this->assertSame('draft', $evaluation->status);
        $this->assertSame('Good proposal', $evaluation->comment);
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);

        // A second autosave must not create duplicates.
        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $scores,
            'comment' => 'Good proposal, updated',
        ])->assertOk();

        $this->assertDatabaseCount('evaluations', 1);
        $this->assertDatabaseCount('evaluation_scores', 6);
    }

    public function test_autosave_rejects_invalid_scores(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);
        $scores[array_key_first($scores)] = 11;

        $this->actingAs($this->judge)
            ->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", ['scores' => $scores])
            ->assertStatus(422);

        $this->actingAs($this->judge)
            ->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
                'scores' => [array_key_first($this->fullScores()) => 0],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_weighted_total_matches_the_formula(): void
    {
        $finalist = $this->makeFinalist();
        $scores = [];
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $scores[$criterion->id] = $criterion->weight <= 15 ? 5 : 3;
        }

        $expected = 0.0;
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $expected += ($scores[$criterion->id] / 5) * (float) $criterion->weight;
        }

        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $scores,
        ])->assertOk();

        $this->assertEqualsWithDelta($expected, (float) Evaluation::first()->total_score, 0.001);
    }

    public function test_submission_requires_all_six_scores(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);
        $scores[array_key_first($scores)] = null;

        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $scores,
        ])->assertOk();

        $this->actingAs($this->judge)
            ->from("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/submit")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('draft', Evaluation::first()->status);
        $this->assertNull(Evaluation::first()->submitted_at);
    }

    public function test_judge_can_submit_a_complete_evaluation(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(5),
        ])->assertOk();

        $this->actingAs($this->judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/submit")
            ->assertRedirect("/judge/category/{$this->category->id}/finalist/{$finalist->id}/submitted");

        $evaluation = Evaluation::first();
        $this->assertSame('submitted', $evaluation->status);
        $this->assertNotNull($evaluation->submitted_at);
        $this->assertEqualsWithDelta(100.0, (float) $evaluation->total_score, 0.001);
    }

    public function test_submitted_evaluation_cannot_be_edited_by_the_judge(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(5),
        ])->assertOk();

        $this->actingAs($this->judge)
            ->post("/judge/category/{$this->category->id}/finalist/{$finalist->id}/submit")
            ->assertRedirect();

        $response = $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(1),
        ]);

        $response->assertStatus(409);
        $this->assertEqualsWithDelta(100.0, (float) Evaluation::first()->total_score, 0.001);
    }

    public function test_duplicate_evaluation_for_the_same_judge_and_finalist_is_prevented(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(4),
        ])->assertOk();

        $this->expectException(QueryException::class);

        DB::table('evaluations')->insert([
            'judge_id' => $this->judge->id,
            'category_id' => $this->category->id,
            'finalist_id' => $finalist->id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_one_judge_does_not_affect_another_judges_evaluation(): void
    {
        $other = User::factory()->judge()->create(['username' => 'other-judge']);
        $finalist = $this->makeFinalist();

        $this->actingAs($other)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(4),
        ])->assertOk();

        $this->actingAs($this->judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->fullScores(5),
        ])->assertOk();

        $this->assertDatabaseCount('evaluations', 2);
        $this->assertEqualsWithDelta(80.0, (float) Evaluation::where('judge_id', $other->id)->first()->total_score, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) Evaluation::where('judge_id', $this->judge->id)->first()->total_score, 0.001);
    }

    public function test_judging_period_can_be_closed_by_the_administrator(): void
    {
        $finalist = $this->makeFinalist();
        CompetitionSetting::put('judging_open', '0');

        $this->actingAs($this->judge)
            ->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
                'scores' => $this->fullScores(4),
            ])
            ->assertForbidden();
    }
}
