<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\JudgingPeriod;
use App\Services\JudgingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly JudgingService $judging,
        private readonly JudgingPeriod $period,
    ) {}

    public function index(): View
    {
        $judge = request()->user();
        $categories = Category::where('is_active', true)->orderBy('display_order')->get();

        $blocks = [];
        foreach ($categories as $category) {
            $finalists = $this->judging->activeFinalists($category);
            $rows = collect();
            $states = $this->judging->statesFor($judge, $finalists);

            foreach ($finalists as $finalist) {
                $rows->push([
                    'finalist' => $finalist,
                    'state' => $states[$finalist->id],
                ]);
            }

            $blocks[$category->id] = [
                'category' => $category,
                'rows' => $rows,
                'total' => $rows->count(),
                'submitted' => $rows->where('state', 'submitted')->count(),
            ];
        }

        return view('judge.dashboard', [
            'judge' => $judge,
            'blocks' => $blocks,
            'judgingOpen' => $this->period->isOpen(),
            'totalFinalists' => collect($blocks)->sum('total'),
            'totalSubmitted' => collect($blocks)->sum('submitted'),
        ]);
    }
}
