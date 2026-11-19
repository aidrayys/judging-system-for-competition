<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\User;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_voting_dashboard_loads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VotingPeriod::create([
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'is_open' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.voting.index'));
        $response->assertOk()
            ->assertSee('Favorite Idea Voting')
            ->assertSee('Registered Codes')
            ->assertSee('Provisional Favorite Idea Ranking');
    }

    public function test_ranking_is_sorted_by_votes_descending_and_shows_actual_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $f1 = Finalist::factory()->create(['category_id' => $category->id, 'name' => 'Finalis A']);
        $f2 = Finalist::factory()->create(['category_id' => $category->id, 'name' => 'Finalis B']);
        $f3 = Finalist::factory()->create(['category_id' => $category->id, 'name' => 'Finalis C']);

        $voter1 = EligibleVoter::factory()->create();
        $voter2 = EligibleVoter::factory()->create();
        $voter3 = EligibleVoter::factory()->create();

        // Rule v2: one vote per idea per voting code.
        $this->createBallot($voter1, [$f1->id, $f2->id]);
        $this->createBallot($voter2, [$f1->id, $f2->id]);
        $this->createBallot($voter3, [$f1->id, $f3->id]);

        // Expected: f1=3, f2=2, f3=1
        VotingPeriod::create([
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'is_open' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.voting.index'));
        $response->assertOk()
            ->assertSeeInOrder(['Finalis A', 'Finalis B', 'Finalis C'])
            ->assertSee('3')
            ->assertSee('2')
            ->assertSee('1');
    }

    public function test_admin_can_reset_single_voter_ballot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $finalist = Finalist::factory()->create(['category_id' => $category->id]);
        $voter = EligibleVoter::factory()->create(['kode_unik' => '11111']);
        $this->createBallot($voter, [$finalist->id]);

        $this->assertDatabaseCount('voting_ballots', 1);

        $response = $this->actingAs($admin)
            ->post(route('admin.voting.voters.reset', $voter), ['confirm' => '1']);

        $response->assertRedirect();
        $this->assertDatabaseCount('voting_ballots', 0);
        $this->assertDatabaseCount('voting_votes', 0);
    }

    public function test_admin_can_reset_all_ballots(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $finalist = Finalist::factory()->create(['category_id' => $category->id]);
        $voter1 = EligibleVoter::factory()->create();
        $voter2 = EligibleVoter::factory()->create();
        $this->createBallot($voter1, [$finalist->id]);
        $this->createBallot($voter2, [$finalist->id]);

        $this->assertDatabaseCount('voting_ballots', 2);

        $response = $this->actingAs($admin)
            ->post(route('admin.voting.reset-all'), ['confirmation' => 'RESET']);

        $response->assertRedirect(route('admin.voting.index'));
        $this->assertDatabaseCount('voting_ballots', 0);
        $this->assertDatabaseCount('voting_votes', 0);
        $this->assertDatabaseCount('eligible_voters', 2);
    }

    public function test_reset_all_requires_reset_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $finalist = Finalist::factory()->create(['category_id' => $category->id]);
        $voter = EligibleVoter::factory()->create();
        $this->createBallot($voter, [$finalist->id]);

        $response = $this->actingAs($admin)
            ->post(route('admin.voting.reset-all'), ['confirmation' => 'HAPUS']);

        $response->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('voting_ballots', 1);
    }

    public function test_reset_is_blocked_when_voting_is_finalized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $finalist = Finalist::factory()->create(['category_id' => $category->id]);
        $voter = EligibleVoter::factory()->create();
        $this->createBallot($voter, [$finalist->id]);

        VotingPeriod::create([
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'is_open' => false,
            'is_finalized' => true,
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.voting.voters.reset', $voter), ['confirm' => '1']);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('voting_ballots', 1);
    }

    private function createBallot($voter, array $finalistIds): void
    {
        $ballot = VotingBallot::create([
            'eligible_voter_id' => $voter->id,
            'submitted_at' => now(),
        ]);
        foreach ($finalistIds as $id) {
            VotingVote::create(['voting_ballot_id' => $ballot->id, 'finalist_id' => $id, 'count' => 1]);
        }
    }
}
