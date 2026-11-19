<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Public Favorite Idea leaderboard (top 3 podium) — UNIQUE ranks, no shared places.
 *
 * Votes per idea = SUM(voting_votes.count) over active finalists with > 0 votes.
 * (Rule v2 stores count = 1 per record; SUM keeps any historical ballot exact.)
 *
 * Ordering (fully deterministic, never random):
 *  1. Total votes DESC.
 *  2. reached_at ASC — the moment the idea reached its FINAL total. Votes are only
 *     ever added, so that moment is the server timestamp of the last ballot that
 *     voted for it: MAX(voting_ballots.submitted_at) (set by the server, not the
 *     browser). Earlier = higher rank. Resolution: 1 second (DB TIMESTAMP).
 *  3. Owner name A–Z (case-insensitive).
 *  4. Finalist id ASC — technical last resort so the order is always total.
 *
 * If reached_at is missing for an idea (should not happen: submitted_at is NOT NULL),
 * no time is invented: that idea is ordered after timestamped ones of equal votes
 * and then alphabetically.
 *
 * Only rank, owner name and idea title leave this class.
 */
class FavoriteIdeaLeaderboard
{
    public const PODIUM_SIZE = 3;

    /** @return Collection<int, array{rank:int, name:string, title:string}> keyed 1..3 */
    public function podium(): Collection
    {
        return $this->order($this->aggregates())
            ->take(self::PODIUM_SIZE)
            ->values()
            ->mapWithKeys(fn ($row, $i) => [$i + 1 => [
                'rank' => $i + 1,
                'name' => (string) $row->name,
                'title' => (string) ($row->project_title ?? ''),
            ]]);
    }

    /** Raw aggregates (internal / tests only — contains vote totals). */
    public function aggregates(): Collection
    {
        return DB::table('voting_votes')
            ->join('voting_ballots', 'voting_ballots.id', '=', 'voting_votes.voting_ballot_id')
            ->join('finalists', 'finalists.id', '=', 'voting_votes.finalist_id')
            ->where('finalists.is_active', true)
            ->groupBy('finalists.id', 'finalists.name', 'finalists.project_title')
            ->havingRaw('SUM(voting_votes.count) > 0')
            ->get([
                'finalists.id',
                'finalists.name',
                'finalists.project_title',
                DB::raw('SUM(voting_votes.count) as total'),
                DB::raw('MAX(voting_ballots.submitted_at) as reached_at'),
            ]);
    }

    /** Deterministic ordering, see class doc. */
    public function order(Collection $rows): Collection
    {
        return $rows->sort(function ($a, $b) {
            $cmp = (int) $b->total <=> (int) $a->total;
            if ($cmp !== 0) {
                return $cmp;
            }

            $ta = $a->reached_at ? strtotime((string) $a->reached_at) : null;
            $tb = $b->reached_at ? strtotime((string) $b->reached_at) : null;
            if ($ta !== $tb) {
                if ($ta === null) {
                    return 1;
                }
                if ($tb === null) {
                    return -1;
                }

                return $ta <=> $tb;
            }

            $cmp = strcmp(mb_strtolower(trim((string) $a->name)), mb_strtolower(trim((string) $b->name)));

            return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
        })->values();
    }
}
