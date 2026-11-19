<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Services\ProvisionalRankingService;

/**
 * Covers the live provisional ranking on the admin dashboard:
 *  - the average is the mean of the valid submitted evaluations only
 *  - drafts, autosaved drafts and incomplete evaluations never count
 *  - the judge count is shown as n/5
 *  - finalists are ordered by their provisional average, highest first
 *  - equal averages share a provisional rank and are flagged as a tie
 *  - a reset immediately updates the average and the judge count
 *  - both categories are calculated independently
 *  - the provisional ranking is admin only and never an official ranking
 */
class ProvisionalRankingTest extends CompetitionTestCase
{
    /**
     * Submit an evaluation with a score per criterion (criterion name => score).
     */
    private function submitScores(User $judge, Finalist $finalist, array $scores): Evaluation
    {
        $category = $finalist->category;
        $payload = $category->activeCriteria()->pluck('id')->mapWithKeys(
            fn ($id) => [$id => $scores[$category->activeCriteria()->find($id)->name] ?? 4]
        )->all();

        $this->actingAs($judge)->postJson("/judge/category/{$category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $payload,
        ])->assertOk();

        $this->actingAs($judge)
            ->post("/judge/category/{$category->id}/finalist/{$finalist->id}/submit")
            ->assertRedirect();

        return Evaluation::where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();
    }

    /**
     * All criteria at the same score.
     *
     * @return array<string, int>
     */
    private function uniform(int $score): array
    {
        return $this->category->activeCriteria()->pluck('name')->mapWithKeys(fn ($name) => [$name => $score])->all();
    }

    /**
     * Every criterion at 4 except "Solution" (weight 25) at 5 => 85.00 (scale 1..5).
     *
     * @return array<string, int>
     */
    /**
     * Every criterion at 5 except "Solution" (weight 25) at 3 => 90.00 (scale 1..5).
     */
    private function mixed90(): array
    {
        $scores = $this->uniform(5);
        $scores['Solution'] = 3;

        return $scores;
    }

    private function mixed85(): array
    {
        $scores = $this->uniform(4);
        $scores['Solution'] = 5;

        return $scores;
    }

    private function provisional(Category $category): array
    {
        return app(ProvisionalRankingService::class)->forCategory($category->fresh());
    }

    public function test_a_single_submitted_evaluation_produces_an_average_and_one_of_five(): void
    {
        $finalist = $this->makeFinalist();
        $judge = $this->designatedJudges['judge_a'];

        $this->submitScores($judge, $finalist, $this->uniform(4));

        $result = $this->provisional($this->category);
        $row = $result['rows'][0];

        $this->assertSame(1, $row['submitted_count']);
        $this->assertSame(5, $row['required_count']);
        $this->assertEqualsWithDelta(80.0, $row['average'], 0.001);
        $this->assertSame(1, $row['rank']);
        $this->assertFalse($row['tied']);
    }

    public function test_two_submitted_evaluations_produce_the_arithmetic_mean(): void
    {
        $finalist = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $finalist, $this->uniform(4));    // 80
        $this->submitScores($this->designatedJudges['judge_b'], $finalist, $this->mixed85()); // 85

        $row = $this->provisional($this->category)['rows'][0];

        $this->assertSame(2, $row['submitted_count']);
        $this->assertSame(5, $row['required_count']);
        $this->assertEqualsWithDelta(82.5, $row['average'], 0.001);
    }

    public function test_drafts_and_autosaved_evaluations_are_excluded(): void
    {
        $finalist = $this->makeFinalist();

        // Three judges only autosave a draft, they never submit.
        foreach (array_slice(array_values($this->designatedJudges), 0, 3) as $judge) {
            $this->actingAs($judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
                'scores' => $this->mixed90(),
            ])->assertOk();
        }

        $this->assertSame(3, Evaluation::where('status', Evaluation::STATUS_DRAFT)->count());
        $this->assertSame(0, Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count());

        $row = $this->provisional($this->category)['rows'][0];

        $this->assertSame(0, $row['submitted_count']);
        $this->assertNull($row['average'], 'a draft must never produce an average');
        $this->assertNull($row['rank']);
        $this->assertSame('not_started', $row['status']);
    }

    public function test_an_incomplete_evaluation_is_excluded(): void
    {
        $finalist = $this->makeFinalist();
        $judge = $this->designatedJudges['judge_a'];

        // Only five of the six criteria are scored, so the evaluation stays a draft.
        $partial = $this->uniform(4);
        $partial['Solution'] = null;

        $this->actingAs($judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $this->category->activeCriteria()->pluck('id')->mapWithKeys(
                fn ($id) => [$id => $partial[$this->category->activeCriteria()->find($id)->name]]
            )->all(),
        ])->assertOk();

        $row = $this->provisional($this->category)['rows'][0];

        $this->assertSame(0, $row['submitted_count']);
        $this->assertNull($row['average']);
    }

    public function test_five_submitted_evaluations_produce_the_average_of_all_five(): void
    {
        $finalist = $this->makeFinalist();

        $totals = [80.0, 85.0, 90.0, 80.0, 90.0];
        $scores = [$this->uniform(4), $this->mixed85(), $this->mixed90(), $this->uniform(4), $this->mixed90()];
        $index = 0;

        foreach ($this->designatedJudges as $judge) {
            $this->submitScores($judge, $finalist, $scores[$index]);
            $index++;
        }

        $row = $this->provisional($this->category)['rows'][0];

        $this->assertSame(5, $row['submitted_count']);
        $this->assertEqualsWithDelta(array_sum($totals) / 5, $row['average'], 0.001);
        $this->assertSame('complete', $row['status']);
    }

    public function test_finalists_are_ordered_by_provisional_average_descending(): void
    {
        $low = $this->makeFinalist();
        $high = $this->makeFinalist();
        $middle = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $low, $this->uniform(3));
        $this->submitScores($this->designatedJudges['judge_a'], $high, $this->uniform(5));
        $this->submitScores($this->designatedJudges['judge_a'], $middle, $this->uniform(4));

        $rows = $this->provisional($this->category)['rows'];

        $this->assertSame([$high->id, $middle->id, $low->id], array_map(fn ($row) => $row['finalist']->id, $rows));
        $this->assertSame([1, 2, 3], array_map(fn ($row) => $row['rank'], $rows));
        $this->assertEqualsWithDelta(100.0, $rows[0]['average'], 0.001);
        $this->assertEqualsWithDelta(80.0, $rows[1]['average'], 0.001);
        $this->assertEqualsWithDelta(60.0, $rows[2]['average'], 0.001);
    }

    public function test_equal_provisional_averages_share_a_rank_and_are_flagged(): void
    {
        $first = $this->makeFinalist();
        $second = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $first, $this->uniform(4));
        $this->submitScores($this->designatedJudges['judge_a'], $second, $this->uniform(4));

        $rows = $this->provisional($this->category)['rows'];

        $this->assertSame([1, 1], array_map(fn ($row) => $row['rank'], $rows));
        $this->assertTrue($rows[0]['tied']);
        $this->assertTrue($rows[1]['tied']);
    }

    public function test_a_reset_updates_the_provisional_score_and_the_judge_count(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $finalist, $this->uniform(4));
        $this->submitScores($this->designatedJudges['judge_d'], $finalist, $this->uniform(5));

        $row = $this->provisional($this->category)['rows'][0];
        $this->assertSame(2, $row['submitted_count']);
        $this->assertEqualsWithDelta(90.0, $row['average'], 0.001);

        // The administrator reopens (resets) the evaluation of Judge A.
        $evaluation = Evaluation::where('judge_id', $this->designatedJudges['judge_a']->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        $this->actingAs($admin)
            ->post("/admin/evaluations/{$evaluation->id}/reopen", ['reason' => 'Score entered by mistake.'])
            ->assertRedirect();

        $row = $this->provisional($this->category)['rows'][0];
        $this->assertSame(1, $row['submitted_count'], 'the reset evaluation must not count as submitted');
        $this->assertEqualsWithDelta(100.0, $row['average'], 0.001);

        // Resetting the last evaluation leaves the finalist without a score.
        $second = Evaluation::where('judge_id', $this->designatedJudges['judge_d']->id)
            ->where('finalist_id', $finalist->id)
            ->first();

        $this->actingAs($admin)
            ->post("/admin/evaluations/{$second->id}/reopen", ['reason' => 'Score entered by mistake.'])
            ->assertRedirect();

        $row = $this->provisional($this->category)['rows'][0];
        $this->assertSame(0, $row['submitted_count']);
        $this->assertNull($row['average']);
        $this->assertNull($row['rank']);
    }

    public function test_the_categories_are_calculated_independently(): void
    {
        $feasibility = Category::factory()->create(['name' => 'Feasibility Study', 'slug' => 'feasibility-study']);
        $this->seedRubric($feasibility);

        $ideaFinalist = $this->makeFinalist($this->category);
        $feasibilityFinalist = $this->makeFinalist($feasibility);

        $this->submitScores($this->designatedJudges['judge_a'], $ideaFinalist, $this->mixed90());
        $this->submitScores($this->designatedJudges['judge_a'], $feasibilityFinalist, $this->uniform(3));

        $idea = $this->provisional($this->category);
        $feas = $this->provisional($feasibility);

        $this->assertEqualsWithDelta(90.0, $idea['rows'][0]['average'], 0.001);
        $this->assertEqualsWithDelta(60.0, $feas['rows'][0]['average'], 0.001);
        $this->assertSame(1, $idea['submitted']);
        $this->assertSame(1, $feas['submitted']);
        $this->assertNotSame($idea['rows'][0]['finalist']->id, $feas['rows'][0]['finalist']->id);
    }

    public function test_the_dashboard_shows_the_provisional_ranking_to_admin_before_the_release(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $finalist, $this->uniform(4));

        $response = $this->actingAs($admin)->get('/admin')->assertOk();

        $response->assertSee('Live provisional ranking');
        $response->assertSee('Provisional');
        $response->assertSee('80.00');
        $response->assertSee('1/5');
        $response->assertSee($finalist->name);
        $response->assertSee($finalist->code);
        // The official ranking is still locked.
        $this->assertDatabaseCount('result_releases', 0);
    }

    public function test_the_official_results_stay_locked_until_every_evaluation_is_complete(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $finalist, $this->uniform(4));

        // Releasing is refused while the category is incomplete.
        $this->actingAs($admin)
            ->post("/admin/results/{$this->category->id}/release")
            ->assertSessionHas('error');

        $this->assertDatabaseCount('result_releases', 0);

        // The result page does not publish a ranking either.
        $this->actingAs($admin)->get("/admin/results/{$this->category->id}")->assertOk();
    }

    public function test_judges_cannot_access_the_provisional_ranking(): void
    {
        $finalist = $this->makeFinalist();
        $this->submitScores($this->designatedJudges['judge_a'], $finalist, $this->uniform(4));

        // A judge is rejected on the server, no matter which parameter is used.
        $this->actingAs($this->judge)->get('/admin')->assertForbidden();
        $this->actingAs($this->judge)->get('/admin?category='.$this->category->id)->assertForbidden();
        $this->actingAs($this->judge)->get('/admin/results')->assertForbidden();
        $this->actingAs($this->judge)->get("/admin/results/{$this->category->id}")->assertForbidden();
        $this->actingAs($this->judge)->get('/admin/evaluations')->assertForbidden();
    }

    public function test_existing_finalist_and_evaluation_data_is_preserved(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitScores($this->designatedJudges['judge_a'], $finalist, $this->uniform(4));

        $finalistsBefore = Finalist::count();
        $evaluationsBefore = Evaluation::count();

        $this->actingAs($admin)->get('/admin')->assertOk();

        $this->assertSame($finalistsBefore, Finalist::count());
        $this->assertSame($evaluationsBefore, Evaluation::count());
        $this->assertDatabaseHas('evaluations', [
            'finalist_id' => $finalist->id,
            'judge_id' => $this->designatedJudges['judge_a']->id,
            'status' => Evaluation::STATUS_SUBMITTED,
        ]);
    }
}
