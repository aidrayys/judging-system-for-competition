<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\VotingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::factory()->create(['slug'=>'idea-generation','name'=>'Idea Generation']);
        Category::factory()->create(['slug'=>'feasibility-study','name'=>'Feasibility Study']);
    }

    public function test_landing_page_is_public_and_links_to_voting_and_login(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('Favorite Idea Voting')
            ->assertSee('Start voting')
            ->assertSee('Sign in');
    }

    public function test_voting_page_asks_for_kode_unik(): void
    {
        $this->createOpenPeriod();
        $this->get('/voting')->assertOk()
            ->assertSee('Voting Code')
            ->assertSee('Enter your 5-digit voting code to begin.');
    }

    public function test_unregistered_kode_unik_is_rejected(): void
    {
        $this->createOpenPeriod();
        $this->post('/voting/verify', ['kode_unik' => '12345'])
            ->assertSessionHasErrors('kode_unik');
    }

    public function test_kode_unik_with_leading_zero_is_rejected(): void
    {
        $this->createOpenPeriod();
        $this->post('/voting/verify', ['kode_unik' => '01234'])
            ->assertSessionHasErrors('kode_unik');
    }

    public function test_kode_unik_shorter_than_5_digits_is_rejected(): void
    {
        $this->createOpenPeriod();
        $this->post('/voting/verify', ['kode_unik' => '1234'])
            ->assertSessionHasErrors('kode_unik');
    }

    public function test_kode_unik_longer_than_5_digits_is_rejected(): void
    {
        $this->createOpenPeriod();
        $this->post('/voting/verify', ['kode_unik' => '123456'])
            ->assertSessionHasErrors('kode_unik');
    }

    public function test_duplicate_kode_unik_cannot_vote_again(): void
    {
        $this->createOpenPeriod();
        $voter = EligibleVoter::factory()->create(['kode_unik'=>'12345']);
        $f = Finalist::factory()->create(['category_id'=>Category::first()->id]);
        $this->post('/voting/verify', ['kode_unik'=>'12345'])->assertRedirect('/voting/ballot');
        $this->post('/voting/ballot', ['votes'=>[$f->id]])->assertRedirect('/voting/thanks');
        $this->get('/voting')->assertOk();
        $this->post('/voting/verify', ['kode_unik'=>'12345'])
            ->assertSessionHasErrors('kode_unik');
    }

    public function test_two_votes_cannot_go_to_same_finalist(): void
    {
        $this->createOpenPeriod();
        EligibleVoter::factory()->create(['kode_unik'=>'12345']);
        $f = Finalist::factory()->create(['category_id'=>Category::first()->id]);
        $this->post('/voting/verify', ['kode_unik'=>'12345'])->assertRedirect('/voting/ballot');
        $this->post('/voting/ballot', ['votes'=>[$f->id,$f->id]])->assertSessionHasErrors();
        $this->assertDatabaseCount('voting_ballots', 0);
        $this->assertDatabaseCount('voting_votes', 0);
    }

    public function test_votes_can_be_split_between_two_finalists(): void
    {
        $this->createOpenPeriod();
        EligibleVoter::factory()->create(['kode_unik'=>'12345']);
        $f1 = Finalist::factory()->create(['category_id'=>Category::first()->id]);
        $f2 = Finalist::factory()->create(['category_id'=>Category::first()->id]);
        $this->post('/voting/verify', ['kode_unik'=>'12345'])->assertRedirect('/voting/ballot');
        $this->post('/voting/ballot', ['votes'=>[$f1->id,$f2->id]])->assertRedirect('/voting/thanks');
        $this->assertDatabaseHas('voting_votes', ['finalist_id'=>$f1->id,'count'=>1]);
        $this->assertDatabaseHas('voting_votes', ['finalist_id'=>$f2->id,'count'=>1]);
    }

    public function test_submission_outside_voting_period_is_rejected(): void
    {
        VotingPeriod::create([
            'opens_at' => now()->subDay(),
            'closes_at' => now()->subHour(),
            'is_open' => true,
        ]);
        EligibleVoter::factory()->create(['kode_unik'=>'12345']);
        $f = Finalist::factory()->create(['category_id'=>Category::first()->id]);
        $this->post('/voting/verify', ['kode_unik'=>'12345'])->assertRedirect('/voting');
        $this->post('/voting/ballot', ['votes'=>[$f->id,$f->id]])->assertRedirect('/voting');
        $this->assertDatabaseMissing('voting_ballots', []);
    }

    private function createOpenPeriod(): void
    {
        VotingPeriod::create([
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'is_open' => true,
        ]);
    }
}
