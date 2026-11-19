<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VotingAdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $period = VotingPeriod::first();
        $now = now(config('app.display_timezone', 'UTC'));
        $status = $period?->statusLabel($now) ?? 'Not Open';

        $totalVoters = EligibleVoter::count();
        $ballots = VotingBallot::count();
        $voted = VotingBallot::distinct('eligible_voter_id')->count('eligible_voter_id');
        $notVoted = max(0, $totalVoters - $voted);
        $participation = $totalVoters ? round($voted / $totalVoters * 100, 2) : 0;
        $totalVotes = DB::table('voting_votes')->sum('count') ?: 0;

        $rankings = Finalist::where('is_active', true)
            ->with('category')
            ->withSum('votingVotes as votes_count', 'count')
            ->orderByDesc('votes_count')
            ->orderBy('code')
            ->get();

        // Assign ranks and detect ties
        $rank = 1;
        $previousVotes = null;
        $displayRank = 1;
        $rankings = $rankings->map(function ($finalist) use (&$rank, &$previousVotes, &$displayRank, $totalVotes) {
            $votes = $finalist->votes_count ?? 0;
            $isTie = $previousVotes !== null && $votes === $previousVotes;
            if (! $isTie) {
                $displayRank = $rank;
            }
            $finalist->rank = $displayRank;
            $finalist->is_tie = $isTie;
            $finalist->vote_percentage = $totalVotes > 0 ? round($votes / $totalVotes * 100, 2) : 0;
            $previousVotes = $votes;
            $rank++;
            return $finalist;
        });

        $tieExists = $rankings->contains('is_tie', true);

        // Final result: highest vote among non top-three winners
        $finalWinner = null;
        $finalTie = false;
        if ($period && $period->is_finalized) {
            $eligible = $rankings->reject(fn ($f) => $f->isTopThreeWinner())->sortByDesc('votes_count');
            $finalWinner = $eligible->first();
            if ($finalWinner) {
                $finalTie = $eligible->where('votes_count', $finalWinner->votes_count)->count() > 1;
            }
        }

        return view('admin.voting.index', compact(
            'period','status','totalVoters','ballots','voted','notVoted','participation','totalVotes','rankings','tieExists','finalWinner','finalTie'
        ));
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'is_open' => ['nullable', 'boolean'],
        ]);

        $tz = config('app.display_timezone', 'UTC');
        $data['opens_at'] = Carbon::parse($data['opens_at'], $tz)->setTimezone('UTC');
        $data['closes_at'] = Carbon::parse($data['closes_at'], $tz)->setTimezone('UTC');
        $data['is_open'] = $request->boolean('is_open', false);

        $period = VotingPeriod::first();
        if ($period) {
            $period->update($data);
        } else {
            $period = VotingPeriod::create($data);
        }

        $this->audit->log('voting.period.update', 'Updated voting period', VotingPeriod::class, $period->id, ['opens_at'=>$period->opens_at,'closes_at'=>$period->closes_at,'is_open'=>$period->is_open]);
        return back()->with('status', 'Voting period updated.');
    }

    public function toggleOpen(Request $request)
    {
        $period = VotingPeriod::first();
        if (! $period) {
            return back()->with('error', 'Atur periode voting terlebih dahulu.');
        }

        $newState = ! $period->is_open;
        $period->update(['is_open' => $newState]);

        $this->audit->log(
            $newState ? 'voting.open' : 'voting.close',
            $newState ? 'Opened Favorite Idea voting' : 'Closed Favorite Idea voting',
            VotingPeriod::class,
            $period->id,
            ['is_open' => $newState]
        );

        return back()->with('status', $newState ? 'Voting dibuka.' : 'Voting ditutup.');
    }

    public function finalize(Request $request)
    {
        $request->validate(['confirm' => ['required', 'accepted']]);

        $period = VotingPeriod::first();
        if (! $period) {
            return back()->with('error', 'Voting period has not been set.');
        }

        if ($period->is_finalized) {
            return back()->with('error', 'Voting has been finalized.');
        }

        $period->update([
            'is_open' => false,
            'is_finalized' => true,
            'finalized_at' => now(config('app.display_timezone', 'UTC'))->setTimezone('UTC'),
        ]);

        $rankings = Finalist::where('is_active', true)
            ->withSum('votingVotes as votes_count', 'count')
            ->orderByDesc('votes_count')
            ->get();

        $eligible = $rankings->reject(fn ($f) => $f->isTopThreeWinner())->sortByDesc('votes_count');
        $winner = $eligible->first();
        $tie = false;
        if ($winner) {
            $tie = $eligible->where('votes_count', $winner->votes_count)->count() > 1;
        }

        $this->audit->log('voting.finalize', 'Finalized Favorite Idea voting', VotingPeriod::class, $period->id, [
            'winner_id' => $winner?->id,
            'tie' => $tie,
        ]);

        return back()->with('status', 'The voting result has been finalized.' . ($tie ? ' There is a tie for the winner position.' : ''));
    }

    public function setWinner(Request $request, Finalist $finalist)
    {
        $data = $request->validate([
            'winner_rank' => ['required', 'integer', 'in:1,2,3'],
        ]);
        $old = $finalist->winner_rank;
        $finalist->update(['winner_rank' => $data['winner_rank']]);
        $this->audit->log('voting.winner.set', "Set {$finalist->code} winner rank to {$data['winner_rank']}", Finalist::class, $finalist->id, ['old'=>$old,'new'=>$data['winner_rank']]);
        return back()->with('status', 'Winner rank updated.');
    }

    public function clearWinner(Finalist $finalist)
    {
        $old = $finalist->winner_rank;
        $finalist->update(['winner_rank' => null]);
        $this->audit->log('voting.winner.clear', "Cleared {$finalist->code} winner rank", Finalist::class, $finalist->id, ['old'=>$old]);
        return back()->with('status', 'Winner rank cleared.');
    }

    public function exportCsv()
    {
        $finalists = Finalist::where('is_active', true)
            ->with('category')
            ->withSum('votingVotes as votes_count', 'count')
            ->orderByDesc('votes_count')
            ->orderBy('code')
            ->get();
        $totalVotes = DB::table('voting_votes')->sum('count') ?: 0;

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="favorite-idea-results.csv"',
        ];
        $callback = function () use ($finalists, $totalVotes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Rank','Code','Name','Department','Category','Project Title','Votes','Vote Percentage','Winner Rank','Excluded']);
            foreach ($finalists as $f) {
                $percentage = $totalVotes > 0 ? round(($f->votes_count ?? 0) / $totalVotes * 100, 2) : 0;
                fputcsv($out, [
                    $f->rank ?? '', $f->code, $f->name, $f->department, $f->category->name, $f->project_title,
                    $f->votes_count ?? 0, $percentage.'%', $f->winner_rank ?? '', $f->isTopThreeWinner() ? 'Yes' : 'No'
                ]);
            }
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function importVoters(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
        ]);

        $file = $request->file('csv');
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return back()->withErrors(['csv' => 'Unable to read the uploaded file.']);
        }

        $header = fgetcsv($handle);
        if (! is_array($header) || empty($header)) {
            fclose($handle);
            return back()->withErrors(['csv' => 'CSV file is empty or has no header.']);
        }

        $header = array_map('strtolower', array_map('trim', $header));
        $kodeIndex = array_search('kode_unik', $header, true);

        if ($kodeIndex === false) {
            fclose($handle);
            return back()->withErrors(['csv' => 'The CSV must contain a "kode_unik" column.']);
        }

        $existingCodes = EligibleVoter::pluck('kode_unik')->filter()->flip();
        $imported = 0;
        $duplicateExisting = 0;
        $duplicateInFile = 0;
        $invalid = 0;
        $seen = [];
        $toInsert = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (! is_array($row) || count($row) <= $kodeIndex) {
                $invalid++;
                continue;
            }

            $kode = trim((string) ($row[$kodeIndex] ?? ''));

            if (! preg_match('/^[1-9]\d{4}$/', $kode)) {
                $invalid++;
                continue;
            }

            if (isset($seen[$kode])) {
                $duplicateInFile++;
                continue;
            }
            $seen[$kode] = true;

            if ($existingCodes->has($kode)) {
                $duplicateExisting++;
                continue;
            }

            $toInsert[] = [
                'nik' => null,
                'kode_unik' => $kode,
                'name' => null,
                'department' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        if (! empty($toInsert)) {
            DB::transaction(function () use ($toInsert, &$imported) {
                foreach (array_chunk($toInsert, 100) as $chunk) {
                    EligibleVoter::insert($chunk);
                    $imported += count($chunk);
                }
            });
        }

        $this->audit->log('voting.voters.import', 'Imported eligible voter codes', null, null, [
            'imported' => $imported,
            'duplicate_existing' => $duplicateExisting,
            'duplicate_in_file' => $duplicateInFile,
            'invalid' => $invalid,
            'filename' => $file->getClientOriginalName(),
        ]);

        $summary = "Imported successfully: {$imported} codes. "
            . "Existing duplicates: {$duplicateExisting}. "
            . "Duplicates in file: {$duplicateInFile}. "
            . "Invalid: {$invalid}.";

        return back()->with('status', $summary);
    }

    public function voters(Request $request)
    {
        $period = VotingPeriod::first();
        $usedIds = VotingBallot::pluck('eligible_voter_id')->flip();

        $voters = EligibleVoter::orderBy('id')
            ->paginate(50)
            ->through(function ($voter) use ($usedIds) {
                $voter->has_voted = $usedIds->has($voter->id);
                return $voter;
            });

        return view('admin.voting.voters', compact('voters', 'period'));
    }

    public function resetVoter(Request $request, EligibleVoter $voter)
    {
        $period = VotingPeriod::first();
        if ($period && $period->is_finalized) {
            return back()->with('error', 'Voting has been finalized. Unfinalize first.');
        }

        $request->validate(['confirm' => ['required', 'accepted']]);

        $deleted = 0;
        DB::transaction(function () use ($voter, &$deleted) {
            $ballot = VotingBallot::where('eligible_voter_id', $voter->id)->first();
            if ($ballot) {
                $ballot->votes()->delete();
                $ballot->delete();
                $deleted = 1;
            }
        });

        $this->audit->log('voting.reset.single', 'Reset single voter ballot', EligibleVoter::class, $voter->id, [
            'deleted_ballots' => $deleted,
            'voter_code_suffix' => substr($voter->kode_unik, -2),
        ]);

        return back()->with('status', 'Voting code reset successfully.');
    }

    public function resetAll(Request $request)
    {
        $period = VotingPeriod::first();
        if ($period && $period->is_finalized) {
            return back()->with('error', 'Voting has been finalized. Unfinalize first.');
        }

        $request->validate([
            'confirmation' => ['required', 'string', 'in:RESET'],
        ]);

        $count = 0;
        DB::transaction(function () use (&$count) {
            $count = VotingBallot::count();
            VotingVote::query()->delete();
            VotingBallot::query()->delete();
        });

        $this->audit->log('voting.reset.all', 'Reset all voting ballots', null, null, [
            'deleted_ballots' => $count,
        ]);

        return redirect()->route('admin.voting.index')->with('status', "All ballots ({$count}) were reset. All voting codes are available again.");
    }

    public function unfinalize(Request $request)
    {
        $period = VotingPeriod::first();
        if (! $period || ! $period->is_finalized) {
            return back()->with('error', 'Voting has not been finalized.');
        }

        $request->validate(['confirm' => ['required', 'accepted']]);

        $period->update([
            'is_finalized' => false,
            'finalized_at' => null,
        ]);

        $this->audit->log('voting.unfinalize', 'Unfinalized Favorite Idea voting', VotingPeriod::class, $period->id);

        return back()->with('status', 'Voting finalization has been cancelled.');
    }
}
