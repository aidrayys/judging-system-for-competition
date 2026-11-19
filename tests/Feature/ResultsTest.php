<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\CompetitionRankingService;

class ResultsTest extends CompetitionTestCase
{
    /**
     * Submit an evaluation of every designated judge for a finalist.
     */
    protected function submitForAllJudges($finalist, int $score = 4): void
    {
        foreach ($this->designatedJudges as $judge) {
            $this->submitEvaluation($judge, $finalist, $score);
        }
    }

    public function test_rankings_are_locked_until_every_required_evaluation_is_submitted(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitEvaluation($this->judge, $finalist, 4);

        $this->actingAs($admin)
            ->get("/admin/results/{$this->category->id}")
            ->assertOk()
            ->assertSee('Results Locked')
            ->assertDontSee('Final ranking');
    }

    public function test_rankings_stay_hidden_until_the_admin_releases_the_results(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitForAllJudges($finalist, 4);

        $this->actingAs($admin)
            ->get("/admin/results/{$this->category->id}")
            ->assertOk()
            ->assertDontSee('Final ranking')
            ->assertSee('Release Results');
    }

    public function test_ranking_is_shown_after_release_with_the_correct_average(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        // Five designated judges with different weighted totals.
        $scores = [5, 5, 5, 3, 3];
        $index = 0;
        foreach ($this->designatedJudges as $judge) {
            $this->submitEvaluation($judge, $finalist, $scores[$index]);
            $index++;
        }

        // (100 + 100 + 100 + 60 + 60) / 5 = 84.00
        $this->actingAs($admin)
            ->post("/admin/results/{$this->category->id}/release")
            ->assertRedirect();

        $this->actingAs($admin)
            ->get("/admin/results/{$this->category->id}")
            ->assertOk()
            ->assertSee('Final ranking')
            ->assertSee($finalist->name)
            ->assertSee('84.00');
    }

    public function test_release_is_rejected_while_evaluations_are_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitEvaluation($this->judge, $finalist, 5);

        $this->actingAs($admin)
            ->from("/admin/results/{$this->category->id}")
            ->post("/admin/results/{$this->category->id}/release")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('result_releases', 0);
    }

    public function test_tied_finalists_share_the_same_rank(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->makeFinalist();
        $second = $this->makeFinalist();

        $this->submitForAllJudges($first, 4);
        $this->submitForAllJudges($second, 4);

        $this->actingAs($admin)->post("/admin/results/{$this->category->id}/release")->assertRedirect();

        $response = $this->actingAs($admin)->get("/admin/results/{$this->category->id}")->assertOk();

        $response->assertSee('Tie');

        // Both finalists share rank 1 (standard competition ranking).
        $ranking = app(CompetitionRankingService::class)->ranking($this->category->fresh());
        $ranks = collect($ranking['rows'])->pluck('rank')->all();

        $this->assertSame([1, 1], $ranks);
        $this->assertTrue(collect($ranking['rows'])->every(fn ($row) => $row['tied']));
    }

    public function test_missing_evaluations_are_not_counted_as_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        // Only one of the five designated judges submitted.
        $this->submitEvaluation($this->judge, $finalist, 4);

        $this->actingAs($admin)->get("/admin/results/{$this->category->id}")->assertSee('Results Locked');
    }

    public function test_judges_cannot_access_the_results_pages(): void
    {
        $this->actingAs($this->judge)->get('/admin/results')->assertForbidden();
        $this->actingAs($this->judge)->get("/admin/results/{$this->category->id}")->assertForbidden();
    }

    public function test_release_state_is_independent_per_category(): void
    {
        $admin = User::factory()->admin()->create();
        $otherCategory = Category::factory()->create(['name' => 'Feasibility Study', 'slug' => 'feasibility-study']);
        $this->seedRubric($otherCategory);
        $finalist = $this->makeFinalist($this->category);

        $this->submitForAllJudges($finalist, 4);

        $this->actingAs($admin)->post("/admin/results/{$this->category->id}/release")->assertRedirect();

        $this->assertDatabaseHas('result_releases', ['category_id' => $this->category->id]);
        $this->assertDatabaseMissing('result_releases', ['category_id' => $otherCategory->id]);
    }

    public function test_audit_log_records_the_release(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->submitForAllJudges($finalist, 4);

        $this->actingAs($admin)->post("/admin/results/{$this->category->id}/release")->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'results.release', 'user_id' => $admin->id]);
    }

    public function test_draft_evaluations_are_not_counted_as_submitted(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        // Only a draft (autosaved, never submitted) for every judge.
        foreach ($this->designatedJudges as $judge) {
            $this->actingAs($judge)->postJson("/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave", [
                'scores' => $this->fullScores(5),
            ])->assertOk();
        }

        $this->assertDatabaseCount('evaluations', 5);
        $this->assertSame(0, Evaluation::where('status', Evaluation::STATUS_SUBMITTED)->count());

        $this->actingAs($admin)->get("/admin/results/{$this->category->id}")->assertSee('Results Locked');
    }
}
