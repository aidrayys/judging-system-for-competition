<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\CompetitionRankingService;
use App\Services\JudgingPeriod;
use App\Services\JudgingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultsController extends Controller
{
    public function __construct(
        private readonly JudgingService $judging,
        private readonly CompetitionRankingService $ranking,
        private readonly JudgingPeriod $period,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $categories = Category::orderBy('display_order')->get();

        $status = [];
        foreach ($categories as $category) {
            $release = $category->resultRelease()->first();

            $status[$category->id] = [
                'required' => $this->judging->requiredEvaluations($category),
                'submitted' => $this->judging->submittedEvaluations($category),
                'remaining' => $this->judging->remainingEvaluations($category),
                'percentage' => $this->judging->completionPercentage($category),
                'complete' => $this->judging->isComplete($category),
                'released' => $release !== null,
                'released_at' => $release?->released_at,
                'review_required' => (bool) $release?->needsReview(),
            ];
        }

        return view('admin.results.index', [
            'categories' => $categories,
            'status' => $status,
            'judgingOpen' => $this->period->isOpen(),
        ]);
    }

    public function show(Category $category): View
    {
        $required = $this->judging->requiredEvaluations($category);
        $submitted = $this->judging->submittedEvaluations($category);
        $complete = $this->judging->isComplete($category);
        $release = $category->resultRelease()->first();

        // The ranking is only calculated for a complete category. Until then
        // no ranking data is produced at all.
        $ranking = $complete ? $this->ranking->ranking($category) : null;

        return view('admin.results.show', [
            'category' => $category,
            'required' => $required,
            'submitted' => $submitted,
            'remaining' => $this->judging->remainingEvaluations($category),
            'percentage' => $this->judging->completionPercentage($category),
            'complete' => $complete,
            'release' => $release,
            'ranking' => $ranking,
            'judges' => $this->ranking->activeJudges(),
            'judgingOpen' => $this->period->isOpen(),
        ]);
    }

    /**
     * Confirm the republication of an official result that was flagged for
     * review by a correction or a reset.
     */
    public function republish(Request $request, Category $category): RedirectResponse
    {
        $release = $category->resultRelease()->first();

        if (! $release) {
            return back()->with('error', 'There is no released result for this category.');
        }

        if (! $release->needsReview()) {
            return back()->with('error', 'The released result of this category is not flagged for review.');
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $release->update([
            'released_at' => now(),
            'reviewed_at' => now(),
            'review_required' => false,
            'note' => $data['note'],
        ]);

        $this->audit->log(
            'results.republish',
            'Republished the official result of '.$category->name.' after a review.',
            Category::class,
            $category->id,
            ['note' => $data['note']]
        );

        return redirect()->route('admin.results.show', $category)->with('status', 'The official result of '.$category->name.' has been reviewed and republished.');
    }

    public function release(Request $request, Category $category): RedirectResponse
    {
        if (! $this->judging->isComplete($category)) {
            return back()->with('error', 'Results cannot be released yet: not every required evaluation has been submitted by all designated judges.');
        }

        if ($category->resultRelease()->exists()) {
            return back()->with('error', 'Results for this category have already been released.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $category->resultRelease()->create([
            'released_by' => $request->user()->id,
            'released_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        $this->audit->log(
            'results.release',
            'Released results for '.$category->name,
            Category::class,
            $category->id,
            ['note' => $data['note'] ?? null, 'submitted' => $this->judging->submittedEvaluations($category)]
        );

        return redirect()->route('admin.results.show', $category)->with('status', 'Results released for '.$category->name.'.');
    }
}
