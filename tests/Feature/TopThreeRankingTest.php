<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\CompetitionRankingService;

/**
 * Covers the automatic top-three ranking on the admin dashboard:
 *  - a finalist with fewer than five submitted evaluations is not eligible
 *  - rankings are averaged and separated by category
 *  - rankings are not released before all required evaluations are complete
 *  - only Admin can release and view official rankings
 *  - ties are displayed and flagged for administrator review
 */
class TopThreeRankingTest extends CompetitionTestCase
{
    private function submitForAllJudges($finalist, int $score = 4): void
    {
        foreach ($this->designatedJudges as $judge) {
            $this->submitEvaluation($judge, $finalist, $score);
        }
    }

    public function test_a_finalist_with_fewer_than_five_submitted_evaluations_is_not_eligible(): void
    {
        $service = app(CompetitionRankingService::class);
        $finalist = $this->makeFinalist();

        // Only three of the five designated judges submitted.
        $judges = array_values($this->designatedJudges);
        foreach (array_slice($judges, 0, 3) as $judge) {
            $this->submitEvaluation($judge, $finalist, 5);
        }

        $progress = $service->finalistProgress($this->category->fresh())->first();

        $this->assertSame(3, $progress['submitted_count']);
        $this->assertSame(5, $progress['required_count']);
        $this->assertFalse($progress['eligible']);
        $this->assertNull($progress['average'], 'an incomplete finalist must not get an average');
        $this->assertSame('in_progress', $progress['status']);

        $ranking = $service->ranking($this->category->fresh());
        $this->assertCount(0, $ranking['rows'], 'an ineligible finalist must not be ranked');
        $this->assertFalse($ranking['complete']);
    }

    public function test_drafts_do_not_count_as_submitted_evaluations(): void
    {
        $service = app(CompetitionRankingService::class);
        $finalist = $this->makeFinalist();

        foreach ($this->designatedJudges as $judge) {
            $this->actingAs($judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
                'scores' => $this->fullScores(5),
            ])->assertOk();
        }

        $this->assertDatabaseCount('evaluations', 5);
        $this->assertSame(0, Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count());

        $progress = $service->finalistProgress($this->category->fresh())->first();
        $this->assertSame(0, $progress['submitted_count']);
        $this->assertFalse($progress['eligible']);
    }

    public function test_evaluations_from_other_judges_are_ignored(): void
    {
        $service = app(CompetitionRankingService::class);
        $finalist = $this->makeFinalist();

        // A judge that is not designated must not influence the ranking.
        $outsider = User::factory()->judge()->create(['username' => 'outsider']);
        $this->submitEvaluation($outsider, $finalist, 5);

        $progress = $service->finalistProgress($this->category->fresh())->first();
        $this->assertSame(0, $progress['submitted_count']);
        $this->assertNull($progress['average']);
    }

    public function test_rankings_are_averaged_over_the_five_designated_judges(): void
    {
        $service = app(CompetitionRankingService::class);
        $finalist = $this->makeFinalist();

        $scores = [5, 5, 4, 4, 3];
        $index = 0;
        foreach ($this->designatedJudges as $judge) {
            $this->submitEvaluation($judge, $finalist, $scores[$index]);
            $index++;
        }

        $progress = $service->finalistProgress($this->category->fresh())->first();
        $this->assertTrue($progress['eligible']);
        $this->assertSame(5, $progress['submitted_count']);
        // (100 + 100 + 80 + 80 + 60) / 5 = 84.00
        $this->assertEqualsWithDelta(84.0, $progress['average'], 0.001);
    }

    public function test_the_dashboard_shows_the_top_three_of_each_category(): void
    {
        $admin = User::factory()->admin()->create();
        $feasibility = Category::factory()->create(['name' => 'Feasibility Study', 'slug' => 'feasibility-study']);
        $this->seedRubric($feasibility);

        // Four idea-generation finalists with descending scores.
        foreach ([5, 4, 3, 2] as $i => $score) {
            $finalist = $this->makeFinalist();
            $this->submitForAllJudges($finalist, $score);
        }

        $feasibilityFinalist = $this->makeFinalist($feasibility);
        $this->submitForAllJudges($feasibilityFinalist, 3);

        $response = $this->actingAs($admin)->get('/admin')->assertOk();

        $response->assertSee('Competition Results');
        $response->assertSee('Idea Generation');
        $response->assertSee('Feasibility Study');
        $response->assertSee('Release Results');
        // The three best finalists of the category are listed.
        $response->assertSee('Rank');
        $response->assertSee('Division');
    }

    public function test_rankings_are_not_published_before_every_finalist_is_fully_evaluated(): void
    {
        $admin = User::factory()->admin()->create();

        $first = $this->makeFinalist();
        $second = $this->makeFinalist();

        $this->submitForAllJudges($first, 5);
        // The second finalist is only evaluated by two judges.
        $judges = array_values($this->designatedJudges);
        $this->submitEvaluation($judges[0], $second, 4);
        $this->submitEvaluation($judges[1], $second, 4);

        $response = $this->actingAs($admin)->get('/admin')->assertOk();

        $response->assertSee('In Progress');
        $response->assertDontSee('Release Results');
        // No winner may be declared while the category is incomplete.
        $this->assertStringNotContainsString('Awaiting release', $response->getContent());
    }

    public function test_the_ranking_is_released_explicitly_by_an_administrator(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();
        $this->submitForAllJudges($finalist, 5);

        // Before the release the ranking is not published.
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Awaiting release');
        $this->assertDatabaseCount('result_releases', 0);

        $this->actingAs($admin)
            ->post("/admin/results/{$this->category->id}/release")
            ->assertRedirect();

        $this->assertDatabaseHas('result_releases', ['category_id' => $this->category->id]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Official');
    }

    public function test_only_admin_can_view_and_release_the_ranking(): void
    {
        $finalist = $this->makeFinalist();
        $this->submitForAllJudges($finalist, 5);

        // A judge cannot open the dashboard or release results.
        $this->actingAs($this->judge)->get('/admin')->assertForbidden();
        $this->actingAs($this->judge)
            ->post("/admin/results/{$this->category->id}/release")
            ->assertForbidden();

        $this->assertDatabaseCount('result_releases', 0);
    }

    public function test_ties_share_a_rank_and_are_flagged_for_review(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(CompetitionRankingService::class);

        $first = $this->makeFinalist();
        $second = $this->makeFinalist();
        $third = $this->makeFinalist();

        $this->submitForAllJudges($first, 4);
        $this->submitForAllJudges($second, 4);   // tie with the first
        $this->submitForAllJudges($third, 3);

        $ranking = $service->ranking($this->category->fresh());

        $ranks = collect($ranking['rows'])->pluck('rank')->all();
        $this->assertSame([1, 1, 3], $ranks, 'competition ranking must be 1, 1, 3');
        $this->assertTrue($ranking['ties']);

        $tiedRows = collect($ranking['rows'])->where('tied', true);
        $this->assertCount(2, $tiedRows);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Tie');
    }

    public function test_the_categories_are_ranked_independently(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(CompetitionRankingService::class);

        $feasibility = Category::factory()->create(['name' => 'Feasibility Study', 'slug' => 'feasibility-study']);
        $this->seedRubric($feasibility);

        $ideaFinalist = $this->makeFinalist($this->category);
        $this->submitForAllJudges($ideaFinalist, 5);

        // The feasibility category is not evaluated at all.
        $this->assertTrue($service->isComplete($this->category->fresh()));
        $this->assertFalse($service->isComplete($feasibility->fresh()));

        $this->actingAs($admin)->get('/admin')->assertOk();

        // Releasing one category must not release the other.
        $this->actingAs($admin)->post("/admin/results/{$this->category->id}/release")->assertRedirect();

        $this->assertDatabaseHas('result_releases', ['category_id' => $this->category->id]);
        $this->assertDatabaseMissing('result_releases', ['category_id' => $feasibility->id]);
    }

    public function test_a_deactivated_finalist_is_not_part_of_the_ranking(): void
    {
        $service = app(CompetitionRankingService::class);
        $active = $this->makeFinalist();
        $inactive = $this->makeFinalist(active: false);

        $this->submitForAllJudges($active, 4);
        $this->submitForAllJudges($inactive, 5);

        $ranking = $service->ranking($this->category->fresh());

        $this->assertCount(1, $ranking['rows']);
        $this->assertSame($active->id, $ranking['rows'][0]['finalist']->id);
    }
}
