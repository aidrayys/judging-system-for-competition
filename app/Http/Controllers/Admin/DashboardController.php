<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\EligibleVoter;
use App\Models\Finalist;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Services\CompetitionRankingService;
use Illuminate\Support\Facades\DB;
use App\Services\JudgingPeriod;
use App\Services\JudgingService;
use App\Services\ProvisionalRankingService;
use App\Services\ScoringService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly JudgingService $judging,
        private readonly ScoringService $scoring,
        private readonly JudgingPeriod $period,
        private readonly CompetitionRankingService $ranking,
        private readonly ProvisionalRankingService $provisional,
    ) {}

    public function index(): View
    {
        $categories = Category::query()->orderBy('display_order')->get();
        $judgingOpen = $this->period->isOpen();

        $categoryStats = [];
        foreach ($categories as $category) {
            $categoryStats[$category->id] = [
                'required' => $this->judging->requiredEvaluations($category),
                'submitted' => $this->judging->submittedEvaluations($category),
                'remaining' => $this->judging->remainingEvaluations($category),
                'percentage' => $this->judging->completionPercentage($category),
                'complete' => $this->judging->isComplete($category),
                'released' => $category->resultRelease()->exists(),
                'weights_valid' => $this->scoring->weightsAreValid($category),
                'weight_total' => $this->scoring->weightTotal($category),
            ];
        }

        $judges = $this->judging->activeJudges();
        $judgeProgress = [];
        foreach ($judges as $judge) {
            $judgeProgress[$judge->id] = [];
            foreach ($categories as $category) {
                $judgeProgress[$judge->id][$category->id] = $this->judging->progressForJudge($judge, $category);
            }
        }

        $requiredTotal = array_sum(array_column($categoryStats, 'required'));
        $submittedTotal = array_sum(array_column($categoryStats, 'submitted'));

        // Competition results (top three) per category. The ranking is only
        // calculated when every finalist of the category is fully evaluated.
        $competitionResults = [];
        foreach ($categories as $category) {
            $complete = $this->judging->isComplete($category);
            $ranking = $complete ? $this->ranking->ranking($category) : null;

            $competitionResults[$category->id] = [
                'category' => $category,
                'complete' => $complete,
                'ranking' => $ranking,
                // Every finalist holding rank 1, 2 or 3. Tied finalists share a
                // rank, so all of them are listed.
                'rows' => $ranking
                    ? collect($ranking['rows'])->filter(fn (array $row) => $row['rank'] <= 3)->values()->all()
                    : [],
                'eligible_count' => $ranking ? $ranking['eligible_count'] : 0,
                'judge_count' => $this->ranking->judgeCount(),
                'ties' => $ranking ? $ranking['ties'] : false,
                'released' => $category->resultRelease()->exists(),
                'released_at' => $category->resultRelease?->released_at,
                'required' => $categoryStats[$category->id]['required'],
                'submitted' => $categoryStats[$category->id]['submitted'],
                'percentage' => $categoryStats[$category->id]['percentage'],
                'finalists_total' => $category->activeFinalists()->count(),
                'finalists_complete' => $this->ranking->finalistProgress($category)->where('eligible', true)->count(),
            ];
        }

        // Favorite Idea Voting summary
        $votingPeriod = VotingPeriod::first();
        $votingStatus = $votingPeriod?->statusLabel(now(config('app.display_timezone', 'UTC'))) ?? 'Not Open';
        $totalVoters = EligibleVoter::count();
        $votedCount = VotingBallot::distinct('eligible_voter_id')->count('eligible_voter_id');
        $totalVotes = DB::table('voting_votes')->sum('count') ?: 0;

        return view('admin.dashboard', [
            'categories' => $categories,
            'categoryStats' => $categoryStats,
            'judges' => $judges,
            'judgeProgress' => $judgeProgress,
            'judgingOpen' => $judgingOpen,
            'activeFinalists' => Finalist::where('is_active', true)->count(),
            'requiredTotal' => $requiredTotal,
            'submittedTotal' => $submittedTotal,
            'remainingTotal' => max(0, $requiredTotal - $submittedTotal),
            'overallPercentage' => $requiredTotal > 0 ? round($submittedTotal / $requiredTotal * 100, 1) : 0.0,
            'openedAt' => $this->period->openedAt(),
            'closedAt' => $this->period->closedAt(),
            'competitionResults' => $competitionResults,
            // Live, provisional ranking per category. It is calculated from the
            // submitted evaluations on the server and is never an official
            // ranking: it is only visible to administrators.
            'provisionalResults' => $this->provisional->forCategories($categories),
            // Favorite Idea Voting summary
            'votingStatus' => $votingStatus,
            'votingTotalVoters' => $totalVoters,
            'votingVoted' => $votedCount,
            'votingTotalVotes' => $totalVotes,
        ]);
    }
}
