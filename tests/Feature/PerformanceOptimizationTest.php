<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Services\CompetitionRankingService;
use App\Services\JudgingService;
use Illuminate\Http\Request;

/**
 * Guards the performance changes of 2026-11-09
 * (docs/performance-optimization/OPTIMIZATION_LOG.md):
 *  - request-scoped memoization in CompetitionRankingService never leaks
 *    stale data into a later request,
 *  - JudgingService::statesFor() (batched) == stateFor() (per finalist),
 *  - the admin pages still show live data across consecutive requests.
 */
class PerformanceOptimizationTest extends CompetitionTestCase
{
    public function test_ranking_memo_is_scoped_to_one_request(): void
    {
        $finalist = $this->makeFinalist();
        $ranking = app(CompetitionRankingService::class);

        $this->assertSame(0, $ranking->submittedEvaluations($this->category));

        $this->submitEvaluation($this->judge, $finalist);

        // A new request (as every HTTP request is) re-reads the database.
        $this->app->instance('request', Request::create('/admin'));
        $this->assertSame(1, $ranking->submittedEvaluations($this->category));
    }

    public function test_admin_dashboard_reflects_new_submissions_on_the_next_request(): void
    {
        $finalist = $this->makeFinalist();
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertViewHas('submittedTotal', 0);

        foreach ($this->designatedJudges as $judge) {
            $this->submitEvaluation($judge, $finalist);
        }

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertViewHas('submittedTotal', 5);
        $this->actingAs($admin)->get('/admin/results/'.$this->category->id)->assertOk();
    }

    public function test_batched_states_match_per_finalist_states(): void
    {
        $service = app(JudgingService::class);
        $notStarted = $this->makeFinalist();
        $draft = $this->makeFinalist();
        $submitted = $this->makeFinalist();
        $locked = $this->makeFinalist();

        Evaluation::create(['judge_id' => $this->judge->id, 'category_id' => $this->category->id, 'finalist_id' => $draft->id, 'status' => Evaluation::STATUS_DRAFT, 'comment' => 'Work in progress']);
        // A blank draft (no scores, no feedback - e.g. after Reset My Score) is "not started".
        $this->submitEvaluation($this->judge, $submitted);
        Evaluation::create(['judge_id' => $this->judge->id, 'category_id' => $this->category->id, 'finalist_id' => $locked->id, 'status' => Evaluation::STATUS_LOCKED]);
        // Another judge's evaluation must never leak into this judge's states.
        $this->submitEvaluation($this->designatedJudges['judge_b'], $notStarted);

        $finalists = collect([$notStarted, $draft, $submitted, $locked]);
        $batched = $service->statesFor($this->judge, $finalists);

        foreach ($finalists as $f) {
            $this->assertSame($service->stateFor($this->judge, $f), $batched[$f->id]);
        }
        $this->assertSame(['not_started', 'draft', 'submitted', 'locked'], array_values($batched));
    }
}
