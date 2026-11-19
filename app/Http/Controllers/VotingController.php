<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use App\Services\FavoriteIdeaLeaderboard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VotingController extends Controller
{
    public function index(Request $request)
    {
        $period = VotingPeriod::first();
        $now = now(config('app.display_timezone', 'UTC'));
        $isOpen = $period && $period->isOpen($now);

        if (! $isOpen) {
            return view('voting.closed', ['period' => $period]);
        }

        $categories = Category::with(['finalists' => fn ($q) => $q->where('is_active', true)->orderBy('display_order')])
            ->orderBy('display_order')->get();

        return view('voting.index', compact('categories', 'period'));
    }

    public function verify(Request $request)
    {
        $request->validate([
            'kode_unik' => ['required', 'regex:/^[1-9]\d{4}$/'],
        ], [
            'kode_unik.regex' => 'The voting code must be exactly 5 digits and cannot start with 0.',
        ]);

        $period = VotingPeriod::first();
        if (! $period || ! $period->isOpen(now(config('app.display_timezone', 'UTC')))) {
            return redirect()->route('voting.public.index')->with('error', 'Voting ditutup.');
        }

        $kodeUnik = $request->input('kode_unik');
        $voter = EligibleVoter::where('kode_unik', $kodeUnik)->first();

        if (! $voter) {
            return back()->withErrors(['kode_unik' => 'Voting code not registered.']);
        }

        if (VotingBallot::where('eligible_voter_id', $voter->id)->exists()) {
            return back()->withErrors(['kode_unik' => 'This voting code has already been used.']);
        }

        session(['voter_id' => $voter->id, 'voter_code' => $voter->kode_unik]);

        return redirect()->route('voting.public.ballot');
    }

    public function ballot()
    {
        $voterId = session('voter_id');
        if (! $voterId) {
            // Back button after a successful vote lands here: show the result.
            return session('voting_completed')
                ? redirect()->route('voting.public.thanks')
                : redirect()->route('voting.public.index');
        }

        // Re-check the code's status from the DB on every visit (stale session,
        // Back/Forward, second tab that already submitted).
        if (VotingBallot::where('eligible_voter_id', $voterId)->exists()) {
            session()->forget(['voter_id', 'voter_code']);

            return redirect()->route('voting.public.index')
                ->withErrors(['kode_unik' => 'This voting code has already been used. Your vote has been recorded.']);
        }

        $period = VotingPeriod::first();
        if (! $period || ! $period->isOpen(now(config('app.display_timezone', 'UTC')))) {
            return view('voting.closed', ['period' => $period]);
        }

        $categories = Category::with(['finalists' => fn ($q) => $q->where('is_active', true)->orderBy('display_order')])
            ->orderBy('display_order')->get();

        return view('voting.ballot', compact('categories'));
    }

    /** Max distinct ideas (= votes) per voting code. One vote per idea. */
    public const MAX_VOTES = 2;

    public function submit(Request $request)
    {
        $voterId = session('voter_id');
        if (! $voterId) {
            return redirect()->route('voting.public.index');
        }

        $period = VotingPeriod::first();
        if (! $period || ! $period->isOpen(now(config('app.display_timezone', 'UTC')))) {
            return redirect()->route('voting.public.index')->with('error', 'Voting is closed.');
        }

        // Rule v2: 1..2 DIFFERENT ideas, one vote each. Enforced server-side.
        $data = $request->validate([
            'votes' => ['required', 'array', 'min:1', 'max:'.self::MAX_VOTES],
            'votes.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('finalists', 'id')->where('is_active', true),
            ],
        ], [
            'votes.required' => 'Choose at least 1 idea.',
            'votes.min' => 'Choose at least 1 idea.',
            'votes.max' => 'You can choose at most 2 ideas.',
            'votes.*.distinct' => 'One idea can only receive 1 vote. Choose 2 different ideas.',
            'votes.*.exists' => 'The selected idea is not valid.',
            'votes.*.integer' => 'The selected idea is not valid.',
        ]);

        $finalistIds = collect($data['votes'])->map(fn ($id) => (int) $id);
        // Defense in depth: "5" and "05" both cast to 5.
        if ($finalistIds->unique()->count() !== $finalistIds->count()) {
            return back()->withErrors(['votes' => 'One idea can only receive 1 vote. Choose 2 different ideas.']);
        }

        try {
            $result = DB::transaction(function () use ($voterId, $finalistIds, $request) {
                // Row lock serialises concurrent submissions for the same voting code.
                $voter = EligibleVoter::whereKey($voterId)->lockForUpdate()->first();
                if (! $voter) {
                    return 'invalid';
                }
                if (VotingBallot::where('eligible_voter_id', $voter->id)->exists()) {
                    return 'used';
                }

                $ballot = VotingBallot::create([
                    'eligible_voter_id' => $voter->id,
                    'submitted_at' => now(config('app.display_timezone', 'UTC')),
                    'ip_address' => $request->ip(),
                ]);

                foreach ($finalistIds as $finalistId) {
                    VotingVote::create([
                        'voting_ballot_id' => $ballot->id,
                        'finalist_id' => $finalistId,
                        'count' => 1,
                    ]);
                }

                return 'ok';
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request won the race (unique eligible_voter_id or
            // unique ballot+finalist). This transaction was rolled back.
            $result = 'used';
        }

        session()->forget(['voter_id', 'voter_code']);

        if ($result !== 'ok') {
            return redirect()->route('voting.public.index')->withErrors([
                'kode_unik' => $result === 'used'
                    ? 'This voting code has already been used.'
                    : 'Voting code not registered.',
            ]);
        }

        session()->regenerateToken();
        session(['voting_completed' => true]);

        return redirect()->route('voting.public.thanks');
    }

    public function thanks(FavoriteIdeaLeaderboard $leaderboard)
    {
        // Only reachable after a successful submission in this session.
        // Read-only page: it never accepts input, so it cannot be used to vote.
        if (! session('voting_completed')) {
            return redirect()->route('voting.public.index');
        }

        $period = VotingPeriod::first();

        return view('voting.thanks', [
            'podium' => $leaderboard->podium(),
            // Ranking is provisional until voting is closed/finalized.
            'provisional' => ! $period || (! $period->is_finalized && $period->isOpen(now(config('app.display_timezone', 'UTC')))),
        ]);
    }
}
