<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Criterion;
use App\Services\AuditLogger;
use App\Services\JudgingPeriod;
use App\Services\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CriterionController extends Controller
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly JudgingPeriod $period,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $categories = Category::orderBy('display_order')->get();
        $selected = $categories->firstWhere('id', $request->integer('category')) ?? $categories->first();

        $judgingOpen = $this->period->isOpen();
        $locked = $judgingOpen && ! $this->hasResetAuthorization($request, $selected);

        return view('admin.criteria.index', [
            'categories' => $categories,
            'category' => $selected,
            'criteria' => $selected ? $selected->criteria()->get() : collect(),
            'weightTotal' => $selected ? $this->scoring->weightTotal($selected) : 0,
            'locked' => $locked,
            'judgingOpen' => $judgingOpen,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        if ($this->rubricIsLocked($request, $category)) {
            return back()->with('error', 'The rubric is locked while the judging period is open. Close the judging period first, or use the audited reset procedure.');
        }

        $data = $request->validate([
            'criteria' => ['required', 'array'],
            'criteria.*.id' => ['required', 'integer', 'exists:criteria,id'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string', 'max:1000'],
            'criteria.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'criteria.*.display_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'criteria.*.is_active' => ['required', 'boolean'],
        ]);

        $total = 0.0;
        foreach ($data['criteria'] as $row) {
            if ((bool) $row['is_active']) {
                $total += (float) $row['weight'];
            }
        }

        if (abs($total - 100.0) > 0.001) {
            return back()->with('error', 'The active criteria weights must total exactly 100%. Current total: '.$total.'%.');
        }

        foreach ($data['criteria'] as $row) {
            Criterion::whereKey($row['id'])
                ->where('category_id', $category->id)
                ->update([
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'weight' => (float) $row['weight'],
                    'display_order' => (int) $row['display_order'],
                    'is_active' => (bool) $row['is_active'],
                    'updated_at' => now(),
                ]);
        }

        $this->audit->log('criteria.update', 'Updated rubric for category '.$category->name, Category::class, $category->id, ['weight_total' => $total]);

        return back()->with('status', 'Rubric updated for '.$category->name.'.');
    }

    /**
     * Edit the category itself (name, description, activation).
     */
    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log('category.update', 'Updated category '.$data['name'], Category::class, $category->id);

        return back()->with('status', 'Category updated.');
    }

    /**
     * Audited reset procedure: allows the rubric to be changed while judging is open,
     * but only with an explicit reason which is written to the audit log.
     */
    public function reset(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->audit->log(
            'criteria.reset',
            'Authorized rubric reset for '.$category->name.': '.$data['reason'],
            Category::class,
            $category->id,
            ['reason' => $data['reason']]
        );

        $request->session()->put('rubric_reset_authorization', [
            'category_id' => $category->id,
            'until' => now()->addMinutes(30)->timestamp,
        ]);

        return back()->with('status', 'Reset authorized for 30 minutes. The reason has been recorded in the audit log.');
    }

    private function hasResetAuthorization(Request $request, ?Category $category): bool
    {
        if (! $category) {
            return false;
        }

        $authorization = $request->session()->get('rubric_reset_authorization');

        return is_array($authorization)
            && ($authorization['category_id'] ?? null) === $category->id
            && ($authorization['until'] ?? 0) > now()->timestamp;
    }

    private function rubricIsLocked(Request $request, Category $category): bool
    {
        if (! $this->period->isOpen()) {
            return false;
        }

        return ! $this->hasResetAuthorization($request, $category);
    }
}
