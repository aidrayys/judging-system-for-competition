<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Judging System for Competition judging update:
 *  - the judging scale is 1..5 on the server for every category
 *  - "Reset My Score" brings the judge back to "Not Started" / "Evaluate"
 *    without touching any other judge, finalist or category
 *  - a failed reset is rolled back completely
 */
class ResetMyScoreAndScaleTest extends CompetitionTestCase
{
    private function url(Finalist $finalist, string $suffix = ''): string
    {
        return "/judge/category/{$finalist->category_id}/finalist/{$finalist->id}{$suffix}";
    }

    private function scores(Finalist $finalist, int $score): array
    {
        return $finalist->category->activeCriteria()->pluck('id')->mapWithKeys(fn ($id) => [$id => $score])->all();
    }

    private function submit(User $judge, Finalist $finalist, int $score, string $comment = 'Feedback'): Evaluation
    {
        $this->actingAs($judge)->postJson($this->url($finalist, '/autosave'), [
            'scores' => $this->scores($finalist, $score),
            'comment' => $comment,
        ])->assertOk();
        $this->actingAs($judge)->post($this->url($finalist, '/submit'))->assertRedirect();

        return Evaluation::where('judge_id', $judge->id)->where('finalist_id', $finalist->id)->sole();
    }

    public function test_scores_above_five_are_rejected_by_autosave_submit_and_correction(): void
    {
        $finalist = $this->makeFinalist();

        foreach ([0, 6, 10] as $invalid) {
            $this->actingAs($this->judge)
                ->postJson($this->url($finalist, '/autosave'), ['scores' => $this->scores($finalist, $invalid)])
                ->assertStatus(422);
        }
        $this->assertDatabaseCount('evaluation_scores', 0);

        $evaluation = $this->submit($this->judge, $finalist, 5);
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $evaluation->status);
        $this->assertEqualsWithDelta(100.0, $evaluation->total_score, 0.001);

        $this->actingAs($this->judge)
            ->postJson($this->url($finalist, '/correct'), ['scores' => $this->scores($finalist, 7)])
            ->assertStatus(422);
        $this->assertEqualsWithDelta(100.0, $evaluation->fresh()->total_score, 0.001);
    }

    public function test_the_evaluation_page_offers_only_scores_one_to_five(): void
    {
        $finalist = $this->makeFinalist();
        $html = $this->actingAs($this->judge)->get($this->url($finalist))->assertOk()->getContent();

        $this->assertStringContainsString('Select 1&ndash;5', $html);
        $this->assertStringNotContainsString('1&ndash;10', $html);
        $criterionId = $this->category->activeCriteria()->first()->id;
        $this->assertStringContainsString('id="score-'.$criterionId.'-5"', $html);
        $this->assertStringNotContainsString('id="score-'.$criterionId.'-6"', $html);
    }

    public function test_reset_my_score_shows_not_started_and_evaluate(): void
    {
        $finalist = $this->makeFinalist();
        $evaluation = $this->submit($this->judge, $finalist, 4, 'Old feedback');

        $this->actingAs($this->judge)->get('/judge')->assertOk()->assertSee('Submitted')->assertSee('View');

        $this->actingAs($this->judge)
            ->post($this->url($finalist, '/reset'))
            ->assertRedirect($this->url($finalist))
            ->assertSessionHas('status');

        $evaluation->refresh();
        $this->assertCount(0, $evaluation->scores);
        $this->assertNull($evaluation->comment);
        $this->assertNull($evaluation->total_score);
        $this->assertNull($evaluation->submitted_at);

        $dashboard = $this->actingAs($this->judge)->get('/judge')->assertOk()->getContent();
        $this->assertStringContainsString('Not Started', $dashboard);
        $this->assertMatchesRegularExpression('/>\s*Evaluate\s*</', $dashboard);
        $this->assertDoesNotMatchRegularExpression('/>\s*Continue\s*</', $dashboard);
        $this->assertStringNotContainsString('>Draft<', $dashboard);

        // The page opens like a brand new evaluation: nothing pre-selected, empty feedback.
        $page = $this->actingAs($this->judge)->get($this->url($finalist))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="score-\d+-\d"[^>]*\schecked/', $page);
        $this->assertStringNotContainsString('Old feedback', $page);
        $this->assertStringContainsString('lastSavedAt: null', $page);

        // Autosave works again and makes it a draft ("Continue").
        $this->actingAs($this->judge)
            ->postJson($this->url($finalist, '/autosave'), ['scores' => [$this->category->activeCriteria()->first()->id => 3]])
            ->assertOk()->assertJsonPath('status', 'draft');
        $this->actingAs($this->judge)->get('/judge')->assertOk()->assertSee('Continue');
    }

    public function test_reset_my_score_never_touches_other_judges_finalists_or_categories(): void
    {
        $other = $this->designatedJudges['judge_b'];
        $finalist = $this->makeFinalist();
        $secondFinalist = $this->makeFinalist();

        $mine = $this->submit($this->judge, $finalist, 4, 'Mine');
        $mineOther = $this->submit($this->judge, $secondFinalist, 3, 'Mine 2');
        $theirs = $this->submit($other, $finalist, 5, 'Theirs');

        $this->actingAs($this->judge)->post($this->url($finalist, '/reset'))->assertRedirect();

        $this->assertCount(0, $mine->fresh()->scores);

        $theirs->refresh();
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $theirs->status);
        $this->assertSame('Theirs', $theirs->comment);
        $this->assertCount(6, $theirs->scores);
        $this->assertEqualsWithDelta(100.0, $theirs->total_score, 0.001);

        $mineOther->refresh();
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $mineOther->status);
        $this->assertEqualsWithDelta(60.0, $mineOther->total_score, 0.001);
        $this->assertCount(6, $mineOther->scores);
    }

    public function test_a_guest_or_an_admin_cannot_use_reset_my_score(): void
    {
        $finalist = $this->makeFinalist();
        $this->submit($this->judge, $finalist, 4);

        // Guests are redirected to the login page and admins are forbidden.
        auth()->logout();
        $this->post($this->url($finalist, '/reset'))->assertRedirect('/login');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post($this->url($finalist, '/reset'))->assertForbidden();

        $this->assertSame(
            Evaluation::STATUS_SUBMITTED,
            Evaluation::where('judge_id', $this->judge->id)->sole()->status
        );
    }

    public function test_a_failed_reset_is_rolled_back_completely(): void
    {
        $finalist = $this->makeFinalist();
        $evaluation = $this->submit($this->judge, $finalist, 4, 'Keep me');

        // The audit entry is written inside the same transaction; make it fail.
        $this->mock(AuditLogger::class, function ($mock) {
            $mock->shouldReceive('log')->andThrow(new \RuntimeException('audit failure'));
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->judge)->post($this->url($finalist, '/reset'));
            $this->fail('The reset should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failure', $e->getMessage());
        }

        $evaluation->refresh();
        $this->assertSame(Evaluation::STATUS_SUBMITTED, $evaluation->status);
        $this->assertSame('Keep me', $evaluation->comment);
        $this->assertCount(6, $evaluation->scores);
        $this->assertEqualsWithDelta(80.0, $evaluation->total_score, 0.001);
    }
}
