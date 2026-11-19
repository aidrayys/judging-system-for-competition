<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;

/**
 * Global Back/Forward (PRG + no-store + server re-validation), judge autosave
 * persistence/isolation, and security checks.
 */
class NavigationAndSecurityTest extends CompetitionTestCase
{
    private function url(Finalist $f, string $suffix = '', ?Category $c = null): string
    {
        $c ??= $this->category;

        return "/judge/category/{$c->id}/finalist/{$f->id}".$suffix;
    }

    private function noStore($response): void
    {
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    // ------------------------------------------------------------ judge flow

    public function test_judge_draft_is_reloaded_from_database_on_back_or_refresh(): void
    {
        $f = $this->makeFinalist();
        $scores = $this->fullScores(3);
        $first = array_key_first($scores);
        $this->actingAs($this->judge)->postJson($this->url($f, '/autosave'), [
            'scores' => [$first => 3], 'comment' => 'Feedback persisted <b>bold</b>',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('evaluations', ['judge_id' => $this->judge->id, 'finalist_id' => $f->id, 'comment' => 'Feedback persisted <b>bold</b>', 'status' => 'draft']);
        $this->assertDatabaseHas('evaluation_scores', ['criterion_id' => $first, 'score' => 3]);

        $page = $this->actingAs($this->judge)->get($this->url($f));
        $page->assertOk();
        $this->noStore($page);
        $html = $page->getContent();
        $this->assertStringContainsString('Feedback persisted &lt;b&gt;bold&lt;/b&gt;', $html); // escaped (XSS-safe)
        $this->assertStringContainsString('lastSavedAt', $html);
        $this->assertMatchesRegularExpression('/submitted:\s*false/', $html);
    }

    public function test_judge_submit_is_prg_and_back_shows_locked_state(): void
    {
        $f = $this->makeFinalist();
        $this->actingAs($this->judge)->postJson($this->url($f, '/autosave'), ['scores' => $this->fullScores(4), 'comment' => 'final'])->assertOk();

        $this->actingAs($this->judge)->post($this->url($f, '/submit'))
            ->assertRedirect(route('judge.submitted', [$this->category, $f]));
        $this->noStore($this->actingAs($this->judge)->get(route('judge.submitted', [$this->category, $f]))->assertOk());

        // Back to the scoring page: rendered fresh from DB, locked.
        $html = $this->actingAs($this->judge)->get($this->url($f))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/submitted:\s*true/', $html);
        $this->assertMatchesRegularExpression('/const readOnly = true/', $html);

        // Old form resubmitted / autosave from a stale tab.
        $this->actingAs($this->judge)->post($this->url($f, '/submit'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->judge)->postJson($this->url($f, '/autosave'), ['scores' => $this->fullScores(1)])->assertStatus(409);

        $ev = Evaluation::sole();
        $this->assertSame('submitted', $ev->status);
        $this->assertEqualsWithDelta(80.0, (float) $ev->total_score, 0.001);
        $this->assertSame('final', $ev->comment);
        $this->assertDatabaseCount('evaluations', 1);
    }

    public function test_failed_submit_does_not_lock(): void
    {
        $f = $this->makeFinalist();
        $scores = $this->fullScores(4);
        array_pop($scores);
        $this->actingAs($this->judge)->postJson($this->url($f, '/autosave'), ['scores' => $scores])->assertOk();
        $this->actingAs($this->judge)->post($this->url($f, '/submit'))->assertSessionHas('error');
        $this->assertSame('draft', Evaluation::sole()->status);
        $this->assertSame(count($scores), Evaluation::sole()->scores()->count());
    }

    public function test_autosave_isolated_between_categories_finalists_and_judges(): void
    {
        $fs = Category::factory()->create(['name' => 'Feasibility Study', 'slug' => 'feasibility-study']);
        $this->seedRubric($fs);
        $ig1 = $this->makeFinalist();
        $ig2 = $this->makeFinalist();
        $fs1 = $this->makeFinalist($fs);
        $other = $this->designatedJudges['judge_d'];

        $fsScores = $fs->activeCriteria()->pluck('id')->mapWithKeys(fn ($id) => [$id => 3])->all();
        $this->actingAs($this->judge)->postJson($this->url($ig1, '/autosave'), ['scores' => $this->fullScores(4), 'comment' => 'IG1'])->assertOk();
        $this->actingAs($this->judge)->postJson($this->url($ig2, '/autosave'), ['scores' => $this->fullScores(5), 'comment' => 'IG2'])->assertOk();
        $this->actingAs($this->judge)->postJson($this->url($fs1, '/autosave', $fs), ['scores' => $fsScores, 'comment' => 'FS1'])->assertOk();
        $this->actingAs($other)->postJson($this->url($ig1, '/autosave'), ['scores' => $this->fullScores(2), 'comment' => 'OTHER'])->assertOk();

        // IG criteria ids sent to the FS endpoint are ignored (no cross-category write).
        $this->actingAs($this->judge)->postJson($this->url($fs1, '/autosave', $fs), ['scores' => $this->fullScores(5), 'comment' => 'FS1'])->assertOk();

        $get = fn ($j, $f) => Evaluation::where('judge_id', $j->id)->where('finalist_id', $f->id)->sole();
        $this->assertSame('IG1', $get($this->judge, $ig1)->comment);
        $this->assertEqualsWithDelta(80.0, (float) $get($this->judge, $ig1)->total_score, 0.001);
        $this->assertSame('IG2', $get($this->judge, $ig2)->comment);
        $this->assertEqualsWithDelta(100.0, (float) $get($this->judge, $ig2)->total_score, 0.001);
        $this->assertSame('FS1', $get($this->judge, $fs1)->comment);
        $this->assertEqualsWithDelta(60.0, (float) $get($this->judge, $fs1)->total_score, 0.001);
        $this->assertSame($fs->id, $get($this->judge, $fs1)->category_id);
        $this->assertSame('OTHER', $get($other, $ig1)->comment);
        $this->assertEqualsWithDelta(40.0, (float) $get($other, $ig1)->total_score, 0.001);
        $this->assertDatabaseCount('evaluations', 4);

        // Finalist/category mismatch in URL is refused.
        $this->actingAs($this->judge)->postJson($this->url($fs1, '/autosave'), ['scores' => $this->fullScores(1)])->assertNotFound();
    }

    // ------------------------------------------------------- auth & roles

    public function test_back_after_logout_does_not_show_protected_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $f = $this->makeFinalist();
        $this->noStore($this->actingAs($this->judge)->get('/judge')->assertOk());
        $this->actingAs($this->judge)->post('/logout')->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->get('/judge')->assertRedirect('/login');
        $this->get($this->url($f))->assertRedirect('/login');

        $this->noStore($this->actingAs($admin)->get('/admin')->assertOk());
    }

    public function test_role_and_url_access_control(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $f = $this->makeFinalist();
        $ev = $this->submitEvaluation($this->designatedJudges['judge_d'], $f);

        $this->app['auth']->forgetGuards();
        $adminUrls = ['/admin', '/admin/voting', '/admin/results', '/admin/evaluations/'.$ev->id, '/admin/voting/export', '/admin/voting/voters'];
        foreach ($adminUrls as $u) {
            $this->get($u)->assertRedirect('/login');                       // guest / voter
        }
        $this->get('/presentations/'.$f->id.'/open')->assertRedirect('/login');
        $this->get('/judge')->assertRedirect('/login');
        foreach ($adminUrls as $u) {
            $this->actingAs($this->judge)->get($u)->assertForbidden();      // judge
        }
        $this->actingAs($admin)->get('/judge')->assertForbidden();

        // Judge A cannot see/modify Judge D's evaluation (only own record is ever loaded).
        $html = $this->actingAs($this->judge)->get($this->url($f))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/submitted:\s*false/', $html);
        $this->actingAs($this->judge)->postJson($this->url($f, '/correct'), ['scores' => $this->fullScores(1)])->assertStatus(403);
        $this->actingAs($this->judge)->post($this->url($f, '/reset'))->assertSessionHas('error');
        $this->assertSame('submitted', $ev->fresh()->status);
        $this->assertEqualsWithDelta(80.0, (float) $ev->fresh()->total_score, 0.001);
    }

    public function test_csrf_is_enforced_on_real_requests(): void
    {
        // Laravel skips CSRF while running tests; use the real middleware with
        // that bypass switched off to prove a POST without token is refused.
        $req = \Illuminate\Http\Request::create('/voting/verify', 'POST', ['kode_unik' => '12345']);
        $req->setLaravelSession(app('session.store'));
        $mw = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $mw->handle($req, fn () => response('ok'));
    }

    // ------------------------------------------------------------ voting security

    public function test_voting_mass_assignment_and_xss(): void
    {
        VotingPeriod::create(['opens_at' => now()->subHour(), 'closes_at' => now()->addHour(), 'is_open' => true]);
        $f = Finalist::factory()->create(['category_id' => $this->category->id, 'name' => '<script>alert(1)</script>', 'project_title' => '"><img src=x onerror=alert(1)>']);
        $victim = EligibleVoter::factory()->create(['kode_unik' => '55555']);
        EligibleVoter::factory()->create(['kode_unik' => '44444']);
        $this->post('/voting/verify', ['kode_unik' => '44444'])->assertRedirect('/voting/ballot');
        $this->post('/voting/ballot', ['votes' => [$f->id], 'eligible_voter_id' => $victim->id, 'voter_id' => $victim->id, 'count' => 9])
            ->assertRedirect('/voting/thanks');

        $this->assertDatabaseMissing('voting_ballots', ['eligible_voter_id' => $victim->id]);
        $this->assertSame(1, (int) VotingVote::sum('count'));

        $html = $this->get('/voting/thanks')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('55555', $html);
        $this->assertStringNotContainsString('44444', $html);
    }

    public function test_sql_injection_in_kode_unik_is_rejected(): void
    {
        VotingPeriod::create(['opens_at' => now()->subHour(), 'closes_at' => now()->addHour(), 'is_open' => true]);
        foreach (["1' OR '1'='1", '12345 OR 1=1', '1;DROP TABLE x'] as $bad) {
            $this->post('/voting/verify', ['kode_unik' => $bad])->assertSessionHasErrors('kode_unik');
        }
        $this->assertNull(session('voter_id'));
    }
}
