<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use Illuminate\Support\Collection;

/**
 * Provisional (live) ranking for administrators.
 *
 * The provisional ranking uses exactly the same evaluation rules as the
 * official competition ranking:
 *  - only the five designated judges (config('judging.designated_judges')) count
 *  - only SUBMITTED evaluations count, drafts and autosaved drafts never do
 *  - an evaluation only counts when it is complete, i.e. it holds a valid
 *    score (1..10) for every active criterion of the category, and when the
 *    server calculated a weighted total for it
 *  - the average is never supplied by the browser, it is derived from the
 *    persisted weighted totals
 *
 * The only difference to the official ranking is that a finalist does not
 * need to be evaluated by all five designated judges to appear:
 *
 *   provisional_average = sum(valid submitted totals) / valid submitted count
 *
 * A finalist without a single valid submission has no average at all; it is
 * shown without a score instead of a fabricated zero.
 *
 * Equal provisional averages share the same rank (standard competition
 * ranking: 1, 1, 3) and are flagged as a tie. No tie-break rule is applied,
 * because none is configured for the official ranking either.
 *
 * The provisional ranking is NOT an official ranking. It is only ever
 * produced for administrators and it is always labelled as provisional.
 */
class ProvisionalRankingService
{
    public function __construct(private readonly CompetitionRankingService $ranking) {}

    /**
     * The provisional ranking of a single category.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     judge_count: int,
     *     finalist_count: int,
     *     fully_evaluated: int,
     *     submitted: int,
     *     required: int,
     *     percentage: float,
     *     complete: bool,
     *     released: bool,
     *     ties: bool
     * }
     */
    public function forCategory(Category $category): array
    {
        $judgeCount = $this->ranking->judgeCount();
        $finalists = $category->activeFinalists()->get();
        $valid = $this->ranking->validSubmittedEvaluations($category);

        $rows = $finalists->map(function (Finalist $finalist) use ($valid, $judgeCount) {
            $evaluations = $valid->get($finalist->id, collect());
            $submittedCount = $evaluations->count();

            $average = null;
            if ($submittedCount > 0) {
                $average = round($evaluations->sum(fn (Evaluation $evaluation) => (float) $evaluation->total_score) / $submittedCount, 2);
            }

            return [
                'finalist' => $finalist,
                'average' => $average,
                'submitted_count' => $submittedCount,
                'required_count' => $judgeCount,
                'missing_count' => max(0, $judgeCount - $submittedCount),
                'status' => $submittedCount === 0
                    ? 'not_started'
                    : ($judgeCount > 0 && $submittedCount === $judgeCount ? 'complete' : 'in_progress'),
            ];
        })->values();

        // Highest provisional average first. Finalists without any valid
        // submission are listed last. Equal averages keep a stable order
        // (more submissions first, then the finalist code) so the table does
        // not jump around between refreshes.
        $sorted = $rows->sort(function (array $a, array $b) {
            $averageA = $a['average'] ?? -1;
            $averageB = $b['average'] ?? -1;

            if ($averageA !== $averageB) {
                return $averageB <=> $averageA;
            }

            if ($a['submitted_count'] !== $b['submitted_count']) {
                return $b['submitted_count'] <=> $a['submitted_count'];
            }

            return strnatcasecmp((string) $a['finalist']->code, (string) $b['finalist']->code);
        })->values();

        // Standard competition ranking for the provisional order.
        $rowsOut = [];
        $previousAverage = null;
        $previousRank = 0;
        $index = 0;

        foreach ($sorted as $row) {
            $index++;
            $average = $row['average'];

            if ($average === null) {
                $rowsOut[] = array_merge($row, ['rank' => null, 'tied' => false]);
                continue;
            }

            if ($previousAverage !== null && abs($average - $previousAverage) < 0.005) {
                $rank = $previousRank;
            } else {
                $rank = $index;
                $previousRank = $rank;
            }

            $rowsOut[] = array_merge($row, ['rank' => $rank, 'tied' => false]);
            $previousAverage = $average;
        }

        // Flag every provisional position that is shared by more than one
        // finalist. No tie-break rule is invented.
        $rankCounts = [];
        foreach ($rowsOut as $row) {
            if ($row['rank'] === null) {
                continue;
            }
            $rankCounts[$row['rank']] = ($rankCounts[$row['rank']] ?? 0) + 1;
        }
        foreach ($rowsOut as $i => $row) {
            $rowsOut[$i]['tied'] = $row['rank'] !== null && ($rankCounts[$row['rank']] ?? 0) > 1;
        }

        $submitted = array_sum(array_column($rowsOut, 'submitted_count'));
        $required = $finalists->count() * $judgeCount;
        $fullyEvaluated = collect($rowsOut)->filter(fn (array $row) => $row['status'] === 'complete')->count();

        return [
            'rows' => $rowsOut,
            'judge_count' => $judgeCount,
            'finalist_count' => $finalists->count(),
            'fully_evaluated' => $fullyEvaluated,
            'submitted' => $submitted,
            'required' => $required,
            'percentage' => $required > 0 ? round(min(100, $submitted / $required * 100), 1) : 0.0,
            'complete' => $required > 0 && $submitted >= $required,
            'released' => $category->resultRelease()->exists(),
            'ties' => collect($rowsOut)->contains('tied', true),
        ];
    }

    /**
     * The provisional ranking for every category, keyed by category id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCategories(Collection $categories): array
    {
        $out = [];

        foreach ($categories as $category) {
            $out[$category->id] = $this->forCategory($category);
        }

        return $out;
    }
}
