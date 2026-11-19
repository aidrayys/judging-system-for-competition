<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Criterion;
use Illuminate\Support\Collection;

/**
 * Central, server-side weighted scoring calculations.
 *
 * Weighted contribution per criterion = (score / MAX_SCORE) x weight
 * where `weight` is stored as a percentage (e.g. 25.00 for 25%).
 * The judging scale is 1..5 (MAX_SCORE = 5). The total is still expressed
 * out of 100 so the existing weights are kept unchanged: the total lies
 * between 20 (all criteria scored 1) and 100 (all criteria scored 5).
 *
 * A score of 5 on a 10% criterion contributes 10 points.
 *
 * This service is the ONLY place where the weighted score is calculated.
 * The frontend preview mirrors this formula for instant feedback, but the
 * persisted value and every reported figure come from here.
 */
class ScoringService
{
    public const MIN_SCORE = 1;

    public const MAX_SCORE = 5;

    /**
     * @param  Collection<int, Criterion>  $criteria
     * @param  array<int|string, int|null>  $scores  criterion_id => score
     */
    public function weightedTotal(Collection $criteria, array $scores): float
    {
        $total = 0.0;

        foreach ($criteria as $criterion) {
            $score = $this->scoreFor($criterion, $scores);

            if ($score === null) {
                continue;
            }

            $total += $this->criterionContribution($criterion, $score);
        }

        return round($total, 2);
    }

    /**
     * @param  Collection<int, Criterion>  $criteria
     * @param  array<int|string, int|null>  $scores
     */
    public function criterionContribution(Criterion $criterion, ?int $score): float
    {
        if ($score === null || ! $this->isValidScore($score)) {
            return 0.0;
        }

        return round(((float) $score / self::MAX_SCORE) * (float) $criterion->weight, 2);
    }

    /**
     * @param  Collection<int, Criterion>  $criteria
     * @param  array<int|string, int|null>  $scores
     * @return array<int, float> criterion_id => contribution
     */
    public function contributions(Collection $criteria, array $scores): array
    {
        $result = [];

        foreach ($criteria as $criterion) {
            $result[$criterion->id] = $this->criterionContribution($criterion, $this->scoreFor($criterion, $scores));
        }

        return $result;
    }

    /**
     * Total weight of the active criteria of a category (in percent).
     */
    public function weightTotal(Category $category): float
    {
        $criteria = $category->activeCriteria()->get();

        return round((float) $criteria->sum('weight'), 2);
    }

    public function weightsAreValid(Category $category): bool
    {
        return abs($this->weightTotal($category) - 100.0) < 0.001;
    }

    public function isValidScore(mixed $score): bool
    {
        if (! is_numeric($score)) {
            return false;
        }

        if ((float) $score != (int) $score) {
            return false;
        }

        $value = (int) $score;

        return $value >= self::MIN_SCORE && $value <= self::MAX_SCORE;
    }

    /**
     * @param  array<int|string, int|null>  $scores
     */
    private function scoreFor(Criterion $criterion, array $scores): ?int
    {
        $score = $scores[$criterion->id] ?? $scores[(string) $criterion->id] ?? null;

        if ($score === null || ! $this->isValidScore($score)) {
            return null;
        }

        return (int) $score;
    }
}
