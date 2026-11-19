<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use App\Services\FavoriteIdeaLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Favorite Idea leaderboard: unique ranks, tie-breaking (votes > time reached > A-Z),
 * visuals (silver logo, podium colours) and voting Back/Forward behaviour.
 */
class LeaderboardFinalTest extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    private int $codeSeq = 20000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = Category::factory()->create(['slug' => 'idea-generation', 'name' => 'Idea Generation']);
        VotingPeriod::create(['opens_at' => now()->subHour(), 'closes_at' => now()->addHour(), 'is_open' => true]);
    }

    private function finalist(array $attrs = []): Finalist
    {
        return Finalist::factory()->create(array_merge(['category_id' => $this->cat->id], $attrs));
    }

    private function castVotes(array $finalists, ?string $at = null, ?EligibleVoter $voter = null): void
    {
        $voter ??= EligibleVoter::factory()->create(['kode_unik' => (string) ++$this->codeSeq]);
        $ballot = VotingBallot::create(['eligible_voter_id' => $voter->id, 'submitted_at' => $at ?? now()]);
        foreach ($finalists as $f) {
            VotingVote::create(['voting_ballot_id' => $ballot->id, 'finalist_id' => $f->id, 'count' => 1]);
        }
    }

    private function login(): EligibleVoter
    {
        $code = (string) ++$this->codeSeq;
        $voter = EligibleVoter::factory()->create(['kode_unik' => $code]);
        $this->post('/voting/verify', ['kode_unik' => $code])->assertRedirect('/voting/ballot');

        return $voter;
    }

    private function podiumNames(): array
    {
        return app(FavoriteIdeaLeaderboard::class)->podium()->map(fn ($s) => $s['name'])->all();
    }

    private function board(): string
    {
        return $this->withSession(['voting_completed' => true])->get('/voting/thanks')->assertOk()->getContent();
    }

    private function step(string $tone, string $name, string $html): void
    {
        $this->assertMatchesRegularExpression('/step--'.$tone.'[^>]*>(?:(?!step--).)*'.preg_quote($name, '/').'/s', $html, "$name should be on $tone podium");
    }

    // ---------------------------------------------------------------- ranking

    public function test_totals_ranks_and_podium_positions(): void
    {
        $gold = $this->finalist(['name' => 'Ani Gold', 'project_title' => 'Idea Gold']);
        $silver = $this->finalist(['name' => 'Bea Silver', 'project_title' => 'Idea Silver']);
        $bronze = $this->finalist(['name' => 'Cici Bronze', 'project_title' => 'Idea Bronze']);
        $fourth = $this->finalist(['name' => 'Dodi Fourth', 'project_title' => 'Idea Fourth']);
        $this->castVotes([$gold, $silver]);
        $this->castVotes([$gold, $silver]);
        $this->castVotes([$gold, $bronze]);
        $this->castVotes([$gold, $bronze]);
        $this->castVotes([$silver, $bronze]);
        $this->castVotes([$silver, $fourth]);
        $this->castVotes([$gold]);

        $totals = app(FavoriteIdeaLeaderboard::class)->aggregates()
            ->mapWithKeys(fn ($r) => [$r->name => (int) $r->total])->sortDesc()->all();
        $this->assertSame(['Ani Gold' => 5, 'Bea Silver' => 4, 'Cici Bronze' => 3, 'Dodi Fourth' => 1], $totals);

        $html = $this->board();
        foreach (['FAVORITE IDEA LEADERBOARD', 'Thank you for voting!', 'Collect ideas. Evaluate fairly. Celebrate innovation.', 'Idea Gold', 'Idea Silver', 'Idea Bronze', 'PROVISIONAL RANKING'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->assertStringNotContainsString('Dodi Fourth', $html);
        $this->assertTrue(strpos($html, 'Bea Silver') < strpos($html, 'Ani Gold') && strpos($html, 'Ani Gold') < strpos($html, 'Cici Bronze'), 'DOM order 2-1-3');
        $this->step('gold', 'Ani Gold', $html);
        $this->step('silver', 'Bea Silver', $html);
        $this->step('bronze', 'Cici Bronze', $html);

        preg_match('/<section class="lb".*?<\/section>/s', $html, $m);
        $visible = strip_tags(preg_replace('/<style.*?<\/style>/s', '', $m[0]));
        $this->assertStringNotContainsString('%', $visible);
        $this->assertDoesNotMatchRegularExpression('/\d+\s*(suara|votes?)\b/i', $visible);
        $this->assertStringNotContainsString('kode', strtolower($visible));
    }

    public function test_tie_broken_by_time_final_total_was_reached(): void
    {
        // Spec example: A=10 @14:05:10, B=10 @14:06:20, C=8 @14:02:00
        $a = $this->finalist(['name' => 'Zed A']);   // alphabetically last on purpose
        $b = $this->finalist(['name' => 'Ana B']);
        $c = $this->finalist(['name' => 'Cik C']);
        for ($i = 0; $i < 9; $i++) {
            $this->castVotes([$a, $b], '2026-11-07 13:00:0'.$i);
        }
        $this->castVotes([$a], '2026-11-07 14:05:10');
        $this->castVotes([$b], '2026-11-07 14:06:20');
        for ($i = 0; $i < 8; $i++) {
            $this->castVotes([$c], '2026-11-07 14:0'.min($i, 2).':00');
        }

        $this->assertSame([1 => 'Zed A', 2 => 'Ana B', 3 => 'Cik C'], $this->podiumNames());
    }

    public function test_tie_with_same_timestamp_broken_alphabetically(): void
    {
        $k = $this->finalist(['name' => 'Karim']);
        $b = $this->finalist(['name' => 'Bella']);
        $this->castVotes([$k, $b], '2026-11-07 10:00:00');

        $this->assertSame([1 => 'Bella', 2 => 'Karim'], $this->podiumNames());
        $html = $this->board();
        $this->step('gold', 'Bella', $html);
        $this->step('silver', 'Karim', $html);
        $this->assertStringNotContainsString('SERI', $html);
    }

    public function test_alphabetical_is_case_insensitive(): void
    {
        $this->castVotes([$this->finalist(['name' => 'bella']), $this->finalist(['name' => 'Alex'])], '2026-11-07 10:00:00');
        $this->assertSame([1 => 'Alex', 2 => 'bella'], $this->podiumNames());
    }

    public function test_ranks_are_unique_exactly_three_and_deterministic(): void
    {
        $fs = collect(range(1, 6))->map(fn ($i) => $this->finalist(['name' => "Same $i"]));
        foreach ($fs->chunk(2) as $pair) {
            $this->castVotes($pair->values()->all(), '2026-11-07 10:00:00');
        }
        $svc = app(FavoriteIdeaLeaderboard::class);
        $p = $svc->podium();
        $this->assertSame([1, 2, 3], $p->keys()->all());
        $this->assertSame(['Same 1', 'Same 2', 'Same 3'], $p->pluck('name')->all());
        $this->assertSame($p->all(), $svc->podium()->all());
        $this->assertSame([1, 2, 3], $p->pluck('rank')->all());
    }

    public function test_leaderboard_updates_with_new_vote(): void
    {
        $a = $this->finalist(['name' => 'Alpha Owner']);
        $b = $this->finalist(['name' => 'Beta Owner']);
        $this->castVotes([$a]);
        $this->assertSame([1 => 'Alpha Owner'], $this->podiumNames());
        $this->castVotes([$b]);
        $this->castVotes([$b]);
        $this->assertSame([1 => 'Beta Owner', 2 => 'Alpha Owner'], $this->podiumNames());
    }

    public function test_fewer_than_three_or_no_votes(): void
    {
        $this->assertStringContainsString('No idea has received votes yet', $this->board());

        $a = $this->finalist(['name' => 'Only One']);
        $this->finalist(['name' => 'Zero Votes']);
        $this->castVotes([$a]);
        $html = $this->board();
        $this->assertStringContainsString('Only One', $html);
        $this->assertStringNotContainsString('Zero Votes', $html);
        $this->assertSame(2, substr_count($html, 'None yet</div>'));
    }

    public function test_inactive_finalist_excluded(): void
    {
        $a = $this->finalist(['name' => 'Active One']);
        $x = $this->finalist(['name' => 'Inactive One']);
        $this->castVotes([$a, $x]);
        $x->update(['is_active' => false]);
        $this->assertSame([1 => 'Active One'], $this->podiumNames());
    }

    public function test_provisional_label_only_while_open(): void
    {
        $this->assertStringContainsString('PROVISIONAL RANKING', $this->board());
        VotingPeriod::first()->update(['is_open' => false]);
        $this->assertStringNotContainsString('PROVISIONAL RANKING', $this->board());
    }

    public function test_url_parameters_cannot_change_ranking(): void
    {
        $a = $this->finalist(['name' => 'Real Winner']);
        $b = $this->finalist(['name' => 'Wannabe']);
        $this->castVotes([$a]);
        $html = $this->withSession(['voting_completed' => true])
            ->get('/voting/thanks?rank[1]='.$b->id.'&finalist_id='.$b->id.'&limit=10&sort=asc')->getContent();
        $this->step('gold', 'Real Winner', $html);
        $this->assertStringNotContainsString('Wannabe', $html);
    }

    public function test_thanks_requires_completed_vote(): void
    {
        $this->get('/voting/thanks')->assertRedirect('/voting');
    }

    // ---------------------------------------------------------------- visuals

    public function test_silver_logo_and_podium_colours(): void
    {
        $html = $this->board();
        $this->assertMatchesRegularExpression('/<img src="[^"]*images\/wordmark-light.svg"/', $html);
        foreach (['#FFD54F', '#DCE3EC', '#CD8B55', '#142B50', 'object-fit: contain'] as $c) {
            $this->assertStringContainsString($c, $html);
        }
    }

    // ---------------------------------------------------------- back / forward

    public function test_back_forward_refresh_after_vote(): void
    {
        $a = $this->finalist(['name' => 'Flow Winner']);
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id]])->assertRedirect('/voting/thanks');
        $this->get('/voting/thanks')->assertOk()->assertSee('Flow Winner');          // PRG target
        $this->get('/voting/ballot')->assertRedirect('/voting/thanks');              // Back
        $this->get('/voting/thanks')->assertOk();                                    // Forward / refresh
        $this->post('/voting/ballot', ['votes' => [$a->id]])->assertRedirect('/voting'); // old form resubmitted
        $this->assertDatabaseCount('voting_ballots', 1);
        $this->assertDatabaseCount('voting_votes', 1);
    }

    public function test_stale_ballot_session_for_used_code_is_rejected_on_get(): void
    {
        $a = $this->finalist();
        $voter = $this->login();
        $this->castVotes([$a], null, $voter);   // used in another tab/device
        $this->get('/voting/ballot')->assertRedirect('/voting')->assertSessionHasErrors('kode_unik');
    }

    public function test_voting_pages_send_no_store(): void
    {
        $this->finalist();
        foreach (['/', '/voting', '/login'] as $url) {
            $this->assertStringContainsString('no-store', (string) $this->get($url)->headers->get('Cache-Control'), $url);
        }
        $this->login();
        $this->assertStringContainsString('no-store', (string) $this->get('/voting/ballot')->headers->get('Cache-Control'));
        $res = $this->withSession(['voting_completed' => true])->get('/voting/thanks');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('pageshow', $res->getContent());
    }
}
