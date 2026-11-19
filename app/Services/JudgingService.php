<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * All progress / completion calculations are derived from the database.
 *
 * The competition counts are delegated to CompetitionRankingService so that
 * the dashboard, the ranking and the release gate always use exactly the
 * same rule (the five designated judges, submitted evaluations only).
 */
class JudgingService
{
    public function __construct(private readonly CompetitionRankingService $ranking) {}

    /**
     * The designated judges that can evaluate.
     *
     * @return Collection<int, User>
     */
    public function activeJudges(): Collection
    {
        return $this->ranking->activeJudges();
    }

    public function activeJudgesCount(): int
    {
        return $this->ranking->judgeCount();
    }

    /**
     * @return Collection<int, Finalist>
     */
    public function activeFinalists(Category $category): Collection
    {
        return $category->activeFinalists()->get();
    }

    public function requiredEvaluations(Category $category): int
    {
        return $this->ranking->requiredEvaluations($category);
    }

    public function submittedEvaluations(Category $category): int
    {
        return $this->ranking->submittedEvaluations($category);
    }

    public function isComplete(Category $category): bool
    {
        return $this->ranking->isComplete($category);
    }

    public function remainingEvaluations(Category $category): int
    {
        return $this->ranking->remainingEvaluations($category);
    }

    public function completionPercentage(Category $category): float
    {
        return $this->ranking->completionPercentage($category);
    }

    /**
     * Per-judge completion for a category.
     *
     * @return array<int, array{judge: User, submitted: int, total: int}>
     */
    public function progressByJudge(Category $category): array
    {
        $finalistIds = $this->activeFinalists($category)->pluck('id');
        $judges = $this->activeJudges();
        $total = $finalistIds->count();

        $submitted = Evaluation::query()
            ->where('category_id', $category->id)
            ->where('status', Evaluation::STATUS_SUBMITTED)
            ->whereIn('finalist_id', $finalistIds)
            ->selectRaw('judge_id, COUNT(*) as aggregate')
            ->groupBy('judge_id')
            ->pluck('aggregate', 'judge_id');

        $out = [];
        foreach ($judges as $judge) {
            $out[$judge->id] = [
                'judge' => $judge,
                'submitted' => (int) ($submitted[$judge->id] ?? 0),
                'total' => $total,
            ];
        }

        return $out;
    }

    /**
     * @return array{submitted: int, total: int}
     */
    public function progressForJudge(User $judge, Category $category): array
    {
        $finalistIds = $this->activeFinalists($category)->pluck('id');

        $submitted = Evaluation::query()
            ->where('category_id', $category->id)
            ->where('judge_id', $judge->id)
            ->where('status', Evaluation::STATUS_SUBMITTED)
            ->whereIn('finalist_id', $finalistIds)
            ->count();

        return ['submitted' => $submitted, 'total' => $finalistIds->count()];
    }

    /**
     * The evaluation state of a single judge/finalist pair (without creating a row).
     */
    public function stateFor(User $judge, Finalist $finalist): string
    {
        $evaluation = Evaluation::query()
            ->where('judge_id', $judge->id)
            ->where('category_id', $finalist->category_id)
            ->where('finalist_id', $finalist->id)
            ->withCount('scores')
            ->first();

        return $this->stateOf($evaluation);
    }

    /**
     * stateFor() for many finalists of one judge with a single query
     * (performance: avoids one query per finalist on the judge dashboard).
     * The (judge_id, category_id, finalist_id) unique key guarantees the
     * result is identical to calling stateFor() per finalist.
     *
     * @param  Collection<int, Finalist>  $finalists
     * @return array<int, string> finalist id => state
     */
    public function statesFor(User $judge, Collection $finalists): array
    {
        if ($finalists->isEmpty()) {
            return [];
        }

        $evaluations = Evaluation::query()
            ->where('judge_id', $judge->id)
            ->whereIn('finalist_id', $finalists->pluck('id'))
            ->withCount('scores')
            ->get()
            ->keyBy(fn (Evaluation $e) => $e->category_id.':'.$e->finalist_id);

        $states = [];
        foreach ($finalists as $finalist) {
            $states[$finalist->id] = $this->stateOf($evaluations->get($finalist->category_id.':'.$finalist->id));
        }

        return $states;
    }

    private function stateOf(?Evaluation $evaluation): string
    {
        if (! $evaluation) {
            return 'not_started';
        }

        if ($evaluation->isSubmitted()) {
            return 'submitted';
        }

        if ($evaluation->isLocked()) {
            return 'locked';
        }

        // A reset (or never touched) draft has nothing to continue.
        if ($evaluation->isBlankDraft()) {
            return 'not_started';
        }

        return 'draft';
    }
}
