<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\JudgingPeriod;
use App\Services\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly JudgingPeriod $period,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $categories = Category::orderBy('display_order')->get();

        $weights = [];
        foreach ($categories as $category) {
            $weights[$category->id] = $this->scoring->weightTotal($category);
        }

        return view('admin.settings.index', [
            'judgingOpen' => $this->period->isOpen(),
            'openedAt' => $this->period->openedAt(),
            'closedAt' => $this->period->closedAt(),
            'categories' => $categories,
            'weights' => $weights,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $categories = Category::orderBy('display_order')->get();

        foreach ($categories as $category) {
            if (! $this->scoring->weightsAreValid($category)) {
                return back()->with('error', 'The judging period cannot be opened: the criteria weights for '.$category->name.' total '.$this->scoring->weightTotal($category).'%, not 100%.');
            }
        }

        $this->period->open();
        $this->audit->log('judging.open', 'Opened the judging period');

        return back()->with('status', 'The judging period is now OPEN. Judges can score and submit evaluations.');
    }

    public function close(Request $request): RedirectResponse
    {
        $this->period->close();
        $this->audit->log('judging.close', 'Closed the judging period');

        return back()->with('status', 'The judging period is now CLOSED. Judges can no longer save or submit evaluations.');
    }
}
