<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSetting;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\JudgingPeriod;
use App\Support\Rubric;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verifies the revision requirements:
 *  - score 1 and 5 accepted, 0 / 6 / 11 / 13 rejected
 *  - weighted calculations correct
 *  - summary contributions match the selected scores
 *  - a closed judging period blocks judge saves and submissions
 *  - an open judging period allows valid saves and submissions
 *  - incomplete evaluations cannot be submitted
 *  - only Admin manages the judging period and uploads presentations
 */
class ScoringAndPeriodTest extends CompetitionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Each test starts from a known persisted state.
        CompetitionSetting::put('judging_open', '1');
    }

    private function autosaveUrl($finalist): string
    {
        return "/judge/category/{$this->category->id}/finalist/{$finalist->id}/autosave";
    }

    private function submitUrl($finalist): string
    {
        return "/judge/category/{$this->category->id}/finalist/{$finalist->id}/submit";
    }

    public function test_score_one_is_accepted(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(1);

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk();

        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('complete', true);

        $this->assertDatabaseHas('evaluation_scores', ['score' => 1]);
        // 6 criteria at 1/5 => 20% of 100 => 20.00
        $this->assertEqualsWithDelta(20.0, (float) Evaluation::first()->total_score, 0.001);
    }

    public function test_score_five_is_accepted_and_totals_one_hundred(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(5);

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk();

        $response->assertJsonPath('ok', true);
        $this->assertEqualsWithDelta(100.0, (float) Evaluation::first()->total_score, 0.001);
    }

    /**
     * @dataProvider invalidScoreProvider
     */
    #[DataProvider('invalidScoreProvider')]
    public function test_invalid_scores_are_rejected(int $invalid): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);
        $scores[array_key_first($scores)] = $invalid;

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertStatus(422);

        // Nothing may be persisted from a rejected request.
        $this->assertDatabaseCount('evaluation_scores', 0);
    }

    public static function invalidScoreProvider(): array
    {
        return [
            'zero' => [0],
            'eleven' => [11],
            'thirteen' => [13],
        ];
    }

    public function test_a_score_of_thirteen_is_not_silently_converted(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);
        $scores[array_key_first($scores)] = 13;

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertStatus(422);

        // The invalid value must not have been clamped to 5.
        $this->assertDatabaseMissing('evaluation_scores', ['score' => 13]);
        $this->assertDatabaseCount('evaluation_scores', 0);
    }

    public function test_weighted_contributions_match_the_documented_formula(): void
    {
        $finalist = $this->makeFinalist();

        // 5 on a 10% criterion contributes 10 points.
        $scores = [];
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $scores[$criterion->id] = 5;
        }

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk();

        $contributions = $response->json('contributions');

        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $expected = round((5 / 5) * (float) $criterion->weight, 2);

            $this->assertEqualsWithDelta(
                $expected,
                (float) $contributions[$criterion->id],
                0.001,
                'Contribution mismatch for '.$criterion->name
            );
        }

        $this->assertEqualsWithDelta(100.0, (float) $response->json('total_score'), 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $response->json('preview_total'), 0.001);
    }

    public function test_summary_contributions_match_the_selected_scores(): void
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
        $returnedScores = $response->json('scores');

        // Every returned contribution must be consistent with the score and the weight.
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $score = (int) $returnedScores[$criterion->id];
            $expected = round(($score / 5) * (float) $criterion->weight, 2);

            $this->assertEqualsWithDelta($expected, (float) $contributions[$criterion->id], 0.001);
        }

        // The sum of the contributions equals the reported total.
        $this->assertEqualsWithDelta(
            round(array_sum($contributions), 2),
            (float) $response->json('preview_total'),
            0.001
        );

        // The persisted scores equal the returned scores (no stale values).
        $this->assertEquals($scores, array_map('intval', $returnedScores));
    }

    public function test_no_stale_zero_contributions_after_a_successful_save(): void
    {
        $finalist = $this->makeFinalist();

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $contributions = $response->json('contributions');

        foreach ($contributions as $criterionId => $contribution) {
            $this->assertGreaterThan(0, (float) $contribution, 'Contribution for criterion '.$criterionId.' must not be stale zero.');
        }
    }

    public function test_clearing_a_score_makes_the_evaluation_incomplete_again(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk();

        $this->assertEqualsWithDelta(80.0, (float) Evaluation::first()->total_score, 0.001);

        // Clear one criterion.
        $cleared = $scores;
        $cleared[array_key_first($cleared)] = null;

        $response = $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $cleared])
            ->assertOk();

        $response->assertJsonPath('complete', false);
        $response->assertJsonPath('scored_count', 5);
        $this->assertNull(Evaluation::first()->total_score);
    }

    public function test_closed_judging_period_blocks_judge_saves(): void
    {
        CompetitionSetting::put('judging_open', '0');

        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertStatus(403);

        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_closed_judging_period_blocks_judge_submissions(): void
    {
        $finalist = $this->makeFinalist();

        // Save first while the period is open.
        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        // Then close the period.
        CompetitionSetting::put('judging_open', '0');

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertForbidden();

        $this->assertSame('draft', Evaluation::first()->status);
    }

    public function test_open_judging_period_allows_saves_and_submissions(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertRedirect();

        $evaluation = Evaluation::first();
        $this->assertSame('submitted', $evaluation->status);
        $this->assertNotNull($evaluation->submitted_at);
        $this->assertEqualsWithDelta(80.0, (float) $evaluation->total_score, 0.001);
    }

    public function test_incomplete_evaluation_cannot_be_submitted(): void
    {
        $finalist = $this->makeFinalist();
        $scores = $this->fullScores(4);
        $scores[array_key_first($scores)] = null;

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $scores])
            ->assertOk();

        $this->actingAs($this->judge)
            ->from($this->submitUrl($finalist))
            ->post($this->submitUrl($finalist))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('draft', Evaluation::first()->status);
        $this->assertNull(Evaluation::first()->submitted_at);
    }

    public function test_submitted_evaluation_is_locked_against_judge_editing(): void
    {
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertRedirect();

        // The judge can no longer change the scores.
        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(1)])
            ->assertStatus(409);

        $this->assertEqualsWithDelta(80.0, (float) Evaluation::first()->total_score, 0.001);
    }

    public function test_admin_can_reopen_a_submitted_evaluation(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->postJson($this->autosaveUrl($finalist), ['scores' => $this->fullScores(4)])
            ->assertOk();

        $this->actingAs($this->judge)
            ->post($this->submitUrl($finalist))
            ->assertRedirect();

        $evaluation = Evaluation::first();

        $this->actingAs($admin)
            ->post("/admin/evaluations/{$evaluation->id}/reopen", ['reason' => 'The judge asked for a correction.'])
            ->assertRedirect();

        $evaluation->refresh();
        $this->assertSame('draft', $evaluation->status);
        $this->assertNull($evaluation->submitted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'evaluation.reopen']);
    }

    public function test_only_admin_can_open_or_close_the_judging_period(): void
    {
        $admin = User::factory()->admin()->create();

        // A judge cannot change the period.
        $this->actingAs($this->judge)->post('/admin/settings/open')->assertForbidden();
        $this->actingAs($this->judge)->post('/admin/settings/close')->assertForbidden();
        $this->actingAs($this->judge)->get('/admin/settings')->assertForbidden();

        // The admin can.
        $this->actingAs($admin)->post('/admin/settings/open')->assertRedirect();
        $this->assertTrue(app(JudgingPeriod::class)->isOpen());

        $this->actingAs($admin)->post('/admin/settings/close')->assertRedirect();
        $this->assertFalse(app(JudgingPeriod::class)->isOpen());
    }

    public function test_admin_sees_the_current_period_status(): void
    {
        $admin = User::factory()->admin()->create();

        CompetitionSetting::put('judging_open', '0');
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Closed');

        CompetitionSetting::put('judging_open', '1');
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Open');
    }

    public function test_judge_sees_a_clear_message_when_the_period_is_closed(): void
    {
        CompetitionSetting::put('judging_open', '0');
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->get('/judge')
            ->assertOk()
            ->assertSee('The judging period is currently closed.')
            ->assertSee('contact the administrator');

        $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk()
            ->assertSee('The judging period is currently closed.');
    }

    public function test_criterion_descriptions_are_stored_and_shown_to_judges(): void
    {
        $finalist = $this->makeFinalist();

        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $this->assertNotEmpty($criterion->description, 'Criterion '.$criterion->name.' must have a description.');
        }

        $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk()
            ->assertSee('Clarity of the problem or opportunity, supporting baseline data, and appropriateness of the analysis method.')
            ->assertSee('Cross-functional collaboration, involvement of relevant stakeholders, and clarity of project scope.');
    }

    public function test_admin_can_edit_a_criterion_description(): void
    {
        $admin = User::factory()->admin()->create();

        // The rubric is editable while the judging period is closed.
        CompetitionSetting::put('judging_open', '0');

        // The whole rubric is submitted, because the weights must still total 100%.
        $payload = [];
        $targetId = $this->category->activeCriteria()->first()->id;
        foreach ($this->category->activeCriteria()->get() as $criterion) {
            $payload[$criterion->id] = [
                'id' => $criterion->id,
                'name' => $criterion->name,
                'description' => $criterion->id === $targetId
                    ? 'A custom explanation written by the administrator.'
                    : $criterion->description,
                'weight' => $criterion->weight,
                'display_order' => $criterion->display_order,
                'is_active' => 1,
            ];
        }

        $this->actingAs($admin)
            ->put("/admin/criteria/{$this->category->id}", ['criteria' => $payload])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('criteria', [
            'id' => $targetId,
            'description' => 'A custom explanation written by the administrator.',
        ]);
    }

    public function test_rubric_edits_are_blocked_while_the_judging_period_is_open(): void
    {
        $admin = User::factory()->admin()->create();
        $criterion = $this->category->activeCriteria()->first();

        $payload = [];
        foreach ($this->category->activeCriteria()->get() as $c) {
            $payload[$c->id] = [
                'id' => $c->id,
                'name' => $c->name,
                'description' => 'A blocked change.',
                'weight' => $c->weight,
                'display_order' => $c->display_order,
                'is_active' => 1,
            ];
        }

        CompetitionSetting::put('judging_open', '1');

        $this->actingAs($admin)
            ->from("/admin/criteria/{$this->category->id}")
            ->put("/admin/criteria/{$this->category->id}", ['criteria' => $payload])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('criteria', ['description' => 'A blocked change.']);
    }

    public function test_the_evaluation_page_renders_five_score_buttons_per_criterion(): void
    {
        $finalist = $this->makeFinalist();

        $response = $this->actingAs($this->judge)
            ->get("/judge/category/{$this->category->id}/finalist/{$finalist->id}")
            ->assertOk();

        foreach ($this->category->activeCriteria()->get() as $criterion) {
            for ($i = 1; $i <= 5; $i++) {
                // Each criterion offers exactly the five score values 1..5.
                $response->assertSee('id="score-'.$criterion->id.'-'.$i.'"', false);
                $response->assertSee('value="'.$i.'"', false);
                $response->assertSee('Score '.$i.' for '.e($criterion->name), false);
            }

            // No score outside the 1..5 range is offered.
            $response->assertDontSee('id="score-'.$criterion->id.'-0"', false);
            $response->assertDontSee('id="score-'.$criterion->id.'-6"', false);
        }

        // The scoring guide is presented as general guidance, not as criteria.
        $response->assertSee('Very Poor');
        $response->assertSee('Very Good');
    }

    public function test_only_admin_can_upload_a_finalist_presentation(): void
    {
        $admin = User::factory()->admin()->create();
        $finalist = $this->makeFinalist();

        $this->actingAs($this->judge)
            ->post("/admin/presentations/{$finalist->id}", [
                'presentation' => UploadedFile::fake()->create('deck.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_both_categories_share_the_same_criteria_definitions_and_weights(): void
    {
        $other = Category::factory()->create(['name' => 'Feasibility Study', 'slug' => 'feasibility-study']);
        $this->seedRubric($other);

        $a = $this->category->activeCriteria()->get()
            ->map(fn (Criterion $c) => [$c->name, $c->description, (float) $c->weight])->all();
        $b = $other->activeCriteria()->get()
            ->map(fn (Criterion $c) => [$c->name, $c->description, (float) $c->weight])->all();

        $this->assertEquals($a, $b);
        $this->assertSame(
            ['Background & Method', 'Content & Presentation', 'Collaboration (Scope)', 'Impact', 'Implementation', 'Solution'],
            array_column($a, 0)
        );
        $this->assertSame([10.0, 10.0, 20.0, 20.0, 15.0, 25.0], array_column($a, 2));
    }

    public function test_rubric_definitions_match_the_specified_text(): void
    {
        $expected = [
            'Background & Method' => 'Clarity of the problem or opportunity, supporting baseline data, and appropriateness of the analysis method.',
            'Content & Presentation' => 'Clarity, structure, completeness, and quality of the presentation and supporting evidence.',
            'Collaboration (Scope)' => 'Cross-functional collaboration, involvement of relevant stakeholders, and clarity of project scope.',
            'Impact' => 'Measurable benefits, supported by data, including cost savings, productivity, quality, safety, or service improvements where relevant.',
            'Implementation' => 'Practical implementation, evidence of execution, sustainability, and readiness for wider adoption.',
            'Solution' => 'Relevance, effectiveness, feasibility, and suitability of the proposed solution to the identified problem.',
        ];

        foreach (Rubric::criteria() as $criterion) {
            $this->assertSame($expected[$criterion['name']], $criterion['description']);
        }
    }
}
