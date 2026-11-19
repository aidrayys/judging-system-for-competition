<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the official competition ranking.
 *
 * Rules implemented here:
 *  - only the five designated judges (config('judging.designated_judges')) count
 *  - only SUBMITTED evaluations count - drafts and autosaved drafts never do
 *  - an evaluation only counts when it is complete, i.e. it holds a valid
 *    score (1..10) for every active criterion of the category
 *  - a finalist is only eligible for the final ranking when every designated
 *    judge submitted a complete evaluation for it. The average is never
 *    divided by the number of available judges.
 *  - equal averages share the same rank (standard competition ranking:
 *    1, 1, 3). Ties are flagged for administrator review because no
 *    tie-break policy is configured.
 *
 * Everything is calculated on the server from persisted data.
 */
class CompetitionRankingService
{
    /**
     * @return array<string, string> username => display name
     */
    public function designatedJudgeDefinitions(): array
    {
        return (array) config('judging.designated_judges', []);
    }

    /**
     * The designated judge accounts, in the configured order.
     *
     * @return Collection<int, User>
     */
    public function designatedJudges(): Collection
    {
        return $this->remember('designated_judges', fn () => $this->loadDesignatedJudges());
    }

    /**
     * Request-scoped memoization (performance).
     *
     * The ranking inputs are read many times while rendering one page (the
     * admin dashboard asked for the designated judges 33 times = 165 queries).
     * The value is stored on the CURRENT request's attribute bag, so it lives
     * for exactly one HTTP request: a new request (or a new test request)
     * always re-reads the database. The ranking rules are unchanged.
     */
    private function remember(string $key, \Closure $resolver): mixed
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request) {
            return $resolver();
        }

        $key = 'judging.ranking.'.$key;
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $resolver());
        }

        return $request->attributes->get($key);
    }

    /**
     * @return Collection<int, User>
     */
    private function loadDesignatedJudges(): Collection
    {
        $judges = collect();

        foreach ($this->designatedJudgeDefinitions() as $username => $displayName) {
            $judge = User::query()
                ->where('role', User::ROLE_JUDGE)
                ->where(function ($query) use ($username, $displayName) {
                    $query->where('username', $username)
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower((string) $displayName)]);
                })
                ->orderBy('id')
                ->first();

            if ($judge && ! $judges->contains('id', $judge->id)) {
                $judges->push($judge);
            }
        }

        return $judges;
    }

    /**
     * Designated judges that are able to evaluate (existing and active).
     *
     * @return Collection<int, User>
     */
    public function activeJudges(): Collection
    {
        return $this->designatedJudges()->filter(fn (User $judge) => (bool) $judge->is_active)->values();
    }

    public function judgeCount(): int
    {
        return $this->activeJudges()->count();
    }

    public function requiredEvaluations(Category $category): int
    {
        return $category->activeFinalists()->count() * $this->judgeCount();
    }

    public function submittedEvaluations(Category $category): int
    {
        return $this->validSubmittedEvaluations($category)
            ->sum(fn (Collection $evaluations) => $evaluations->count());
    }

    /**
     * Every valid, complete evaluation of a category that was submitted by one
     * of the designated judges, grouped by finalist id.
     *
     * This is the single place where "a counting evaluation" is defined. The
     * official ranking (CompetitionRankingService) and the provisional
     * ranking (ProvisionalRankingService) both use it, so both views always
     * agree on which evaluations are valid.
     *
     * @return Collection<int, Collection<int, Evaluation>>
     */
    public function validSubmittedEvaluations(Category $category): Collection
    {
        return $this->remember('valid_submitted.'.$category->id, fn () => $this->loadValidSubmittedEvaluations($category));
    }

    /**
     * @return Collection<int, Collection<int, Evaluation>>
     */
    private function loadValidSubmittedEvaluations(Category $category): Collection
    {
        $finalistIds = $category->activeFinalists()->pluck('id');
        $judgeIds = $this->activeJudges()->pluck('id');

        if ($finalistIds->isEmpty() || $judgeIds->isEmpty()) {
            return collect();
        }

        $criteriaCount = $category->activeCriteria()->count();

        return Evaluation::query()
            ->where('category_id', $category->id)
            ->where('status', Evaluation::STATUS_SUBMITTED)
            ->whereIn('finalist_id', $finalistIds)
            ->whereIn('judge_id', $judgeIds)
            ->with('scores')
            ->get()
            ->filter(fn (Evaluation $evaluation) => $this->isCompleteEvaluation($evaluation, $criteriaCount))
            ->groupBy('finalist_id');
    }

    public function isComplete(Category $category): bool
    {
        $required = $this->requiredEvaluations($category);

        return $required > 0 && $this->submittedEvaluations($category) >= $required;
    }

    public function remainingEvaluations(Category $category): int
    {
        return max(0, $this->requiredEvaluations($category) - $this->submittedEvaluations($category));
    }

    public function completionPercentage(Category $category): float
    {
        $required = $this->requiredEvaluations($category);

        if ($required === 0) {
            return 0.0;
        }

        return round(min(100, $this->submittedEvaluations($category) / $required * 100), 1);
    }

    /**
     * Per finalist progress for a category.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function finalistProgress(Category $category): Collection
    {
        $finalists = $category->activeFinalists()->get();
        $judgeIds = $this->activeJudges()->pluck('id');
        $judgeCount = $this->judgeCount();

        $evaluations = $this->validSubmittedEvaluations($category);

        return $finalists->map(function (Finalist $finalist) use ($evaluations, $judgeCount, $judgeIds) {
            // validSubmittedEvaluations() already only contains submitted and
            // complete evaluations of the designated judges.
            $valid = $evaluations->get($finalist->id, collect())->values();

            $submittedCount = $valid->count();
            $eligible = $judgeCount > 0 && $submittedCount === $judgeCount;

            $average = null;
            if ($eligible) {
                $average = round($valid->sum(fn (Evaluation $e) => (float) $e->total_score) / $submittedCount, 2);
            }

            // The weighted total of every designated judge, for the detail view.
            $judgeTotals = [];
            foreach ($judgeIds as $judgeId) {
                $judgeTotals[$judgeId] = $valid
                    ->firstWhere('judge_id', $judgeId)
                    ? (float) $valid->firstWhere('judge_id', $judgeId)->total_score
                    : null;
            }

            return [
                'finalist' => $finalist,
                'submitted_count' => $submittedCount,
                'required_count' => $judgeCount,
                'average' => $average,
                'eligible' => $eligible,
                'status' => $eligible ? 'complete' : 'in_progress',
                'missing_judges' => $judgeCount - $submittedCount,
                'judge_totals' => $judgeTotals,
            ];
        })->values();
    }

    /**
     * The official ranking of a category.
     *
     * Only finalists with a complete set of designated judge evaluations are
     * ranked. Ties share a rank (standard competition ranking).
     *
     * @return array{rows: array<int, array<string, mixed>>, eligible_count: int, judge_count: int, complete: bool, ties: bool}
     */
    public function ranking(Category $category): array
    {
        $progress = $this->finalistProgress($category);

        $eligible = $progress->filter(fn (array $row) => $row['eligible'])
            ->sortByDesc(fn (array $row) => $row['average'])
            ->values();

        $rows = [];
        $previousAverage = null;
        $previousRank = 0;
        $index = 0;

        foreach ($eligible as $row) {
            $index++;
            $average = (float) $row['average'];

            if ($previousAverage !== null && abs($average - $previousAverage) < 0.005) {
                $rank = $previousRank;
            } else {
                $rank = $index;
                $previousRank = $rank;
            }

            $rows[] = array_merge($row, ['rank' => $rank]);
            $previousAverage = $average;
        }

        // Flag every position that is shared by more than one finalist.
        $rankCounts = [];
        foreach ($rows as $row) {
            $rankCounts[$row['rank']] = ($rankCounts[$row['rank']] ?? 0) + 1;
        }
        foreach ($rows as $i => $row) {
            $rows[$i]['tied'] = $rankCounts[$row['rank']] > 1;
        }

        return [
            'rows' => $rows,
            'judges' => $this->activeJudges(),
            'judge_count' => $this->judgeCount(),
            'eligible_count' => count($rows),
            'complete' => $this->isComplete($category),
            'ties' => collect($rows)->contains('tied', true),
        ];
    }

    /**
     * An evaluation counts when it is submitted and holds a valid score for
     * every active criterion of the category.
     */
    private function isCompleteEvaluation(Evaluation $evaluation, int $criteriaCount): bool
    {
        if (! $evaluation->isSubmitted()) {
            return false;
        }

        if ($evaluation->total_score === null) {
            return false;
        }

        if ($criteriaCount === 0) {
            return false;
        }

        return $evaluation->scores->count() === $criteriaCount;
    }
}
