<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Voting rule v2 (max 2 votes, 2 DIFFERENT ideas) + Kahoot-style leaderboard.
 */
class VotingRulesV2Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

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

    private int $codeSeq = 10000;

    private function login(?string $code = null): EligibleVoter
    {
        $code ??= (string) ++$this->codeSeq;
        $voter = EligibleVoter::factory()->create(['kode_unik' => $code]);
        $this->post('/voting/verify', ['kode_unik' => $code])->assertRedirect('/voting/ballot');

        return $voter;
    }

    /** Cast a ballot directly in DB for leaderboard setup. */
    private function castVotes(array $finalists): void
    {
        $voter = EligibleVoter::factory()->create(['kode_unik' => (string) ++$this->codeSeq]);
        $ballot = VotingBallot::create(['eligible_voter_id' => $voter->id, 'submitted_at' => now()]);
        foreach ($finalists as $f) {
            VotingVote::create(['voting_ballot_id' => $ballot->id, 'finalist_id' => $f->id, 'count' => 1]);
        }
    }

    // ---------- Voting ----------

    public function test_1_one_idea_passes(): void
    {
        $f = $this->finalist();
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$f->id]])->assertRedirect('/voting/thanks');
        $this->assertDatabaseCount('voting_votes', 1);
        $this->assertDatabaseHas('voting_votes', ['finalist_id' => $f->id, 'count' => 1]);
    }

    public function test_2_two_different_ideas_pass(): void
    {
        [$a, $b] = [$this->finalist(), $this->finalist()];
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id, $b->id]])->assertRedirect('/voting/thanks');
        $this->assertDatabaseHas('voting_votes', ['finalist_id' => $a->id, 'count' => 1]);
        $this->assertDatabaseHas('voting_votes', ['finalist_id' => $b->id, 'count' => 1]);
        $this->assertDatabaseCount('voting_votes', 2);
    }

    public function test_3_two_votes_same_idea_blocked(): void
    {
        $a = $this->finalist();
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id, $a->id]])->assertSessionHasErrors('votes.1');
        $this->post('/voting/ballot', ['votes' => [$a->id, (string) $a->id]])->assertSessionHasErrors();
        $this->assertDatabaseCount('voting_ballots', 0);
    }

    public function test_3b_count_field_manipulation_ignored(): void
    {
        $a = $this->finalist();
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id], 'count' => 2, 'votes_count' => [$a->id => 2]])
            ->assertRedirect('/voting/thanks');
        $this->assertSame(1, (int) VotingVote::sum('count'));
    }

    public function test_4_three_ideas_blocked(): void
    {
        [$a, $b, $c] = [$this->finalist(), $this->finalist(), $this->finalist()];
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id, $b->id, $c->id]])->assertSessionHasErrors('votes');
        $this->assertDatabaseCount('voting_ballots', 0);
    }

    public function test_5_select_two_cancel_one_submit_passes(): void
    {
        // Client sends only the remaining selection.
        [$a] = [$this->finalist(), $this->finalist()];
        $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id]])->assertRedirect('/voting/thanks');
        $this->assertDatabaseCount('voting_votes', 1);
    }

    public function test_6_empty_submission_blocked(): void
    {
        $this->login();
        $this->post('/voting/ballot', [])->assertSessionHasErrors('votes');
        $this->post('/voting/ballot', ['votes' => []])->assertSessionHasErrors('votes');
        $this->assertDatabaseCount('voting_ballots', 0);
    }

    public function test_7_used_code_blocked(): void
    {
        $a = $this->finalist();
        $this->login('23456');
        $this->post('/voting/ballot', ['votes' => [$a->id]])->assertRedirect('/voting/thanks');
        $this->post('/voting/verify', ['kode_unik' => '23456'])->assertSessionHasErrors('kode_unik');
        $this->assertDatabaseCount('voting_ballots', 1);
    }

    public function test_8_duplicate_request_blocked(): void
    {
        [$a, $b] = [$this->finalist(), $this->finalist()];
        $voter = $this->login();
        $this->post('/voting/ballot', ['votes' => [$a->id]])->assertRedirect('/voting/thanks');
        // Replay of the same POST: session no longer authorised.
        $this->post('/voting/ballot', ['votes' => [$b->id]])->assertRedirect('/voting');
        // Race: stale session still holding voter_id after a ballot exists.
        $this->withSession(['voter_id' => $voter->id])
            ->post('/voting/ballot', ['votes' => [$b->id]])
            ->assertRedirect('/voting')->assertSessionHasErrors('kode_unik');
        $this->assertDatabaseCount('voting_ballots', 1);
        $this->assertDatabaseCount('voting_votes', 1);
    }

    public function test_invalid_or_inactive_idea_blocked(): void
    {
        $inactive = $this->finalist(['is_active' => false]);
        $this->login();
        $this->post('/voting/ballot', ['votes' => [999999]])->assertSessionHasErrors('votes.0');
        $this->post('/voting/ballot', ['votes' => [$inactive->id]])->assertSessionHasErrors('votes.0');
        $this->post('/voting/ballot', ['votes' => ['abc']])->assertSessionHasErrors('votes.0');
        $this->assertDatabaseCount('voting_ballots', 0);
    }

    public function test_failed_save_does_not_consume_code(): void
    {
        $a = $this->finalist();
        $voter = $this->login();
        // Force failure inside the transaction after ballot insert.
        VotingVote::creating(function () { throw new \RuntimeException('boom'); });
        $this->withoutExceptionHandling();
        try {
            $this->post('/voting/ballot', ['votes' => [$a->id]]);
            $this->fail('expected exception');
        } catch (\RuntimeException) {
        }
        VotingVote::flushEventListeners();
        $this->assertDatabaseMissing('voting_ballots', ['eligible_voter_id' => $voter->id]);
    }

    public function test_db_rejects_same_idea_twice_in_one_ballot(): void
    {
        $a = $this->finalist();
        $this->castVotes([$a]);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        VotingVote::create(['voting_ballot_id' => VotingBallot::first()->id, 'finalist_id' => $a->id, 'count' => 1]);
    }

    public function test_ballot_page_has_no_plus_minus_controls(): void
    {
        $this->finalist();
        $this->login();
        $this->get('/voting/ballot')->assertOk()
            ->assertDontSee('vote-btn plus', false)
            ->assertDontSee('Tambah suara')
            ->assertSee('Votes selected:', false);
    }
}
