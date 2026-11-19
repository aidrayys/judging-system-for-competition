<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinalistController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $query = Finalist::query()->with('category')->orderBy('category_id')->orderBy('display_order')->orderBy('id');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('project_title', 'like', $term)
                    ->orWhere('department', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        return view('admin.finalists.index', [
            'finalists' => $query->paginate(20)->withQueryString(),
            'categories' => Category::orderBy('display_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.finalists.create', [
            'categories' => Category::orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $finalist = Finalist::create($data);
        $this->audit->log('finalist.create', 'Created finalist '.$finalist->code.' - '.$finalist->name, Finalist::class, $finalist->id, ['code' => $finalist->code]);

        return redirect()->route('admin.finalists.index')->with('status', 'Finalist created.');
    }

    public function edit(Finalist $finalist): View
    {
        return view('admin.finalists.edit', [
            'finalist' => $finalist,
            'categories' => Category::orderBy('display_order')->get(),
        ]);
    }

    public function update(Request $request, Finalist $finalist): RedirectResponse
    {
        $data = $this->validated($request, $finalist);

        $finalist->update($data);
        $this->audit->log('finalist.update', 'Updated finalist '.$finalist->code.' - '.$finalist->name, Finalist::class, $finalist->id);

        return redirect()->route('admin.finalists.index')->with('status', 'Finalist updated.');
    }

    public function toggle(Finalist $finalist): RedirectResponse
    {
        $finalist->update(['is_active' => ! $finalist->is_active]);
        $this->audit->log(
            'finalist.toggle',
            ($finalist->is_active ? 'Activated' : 'Deactivated').' finalist '.$finalist->code.' - '.$finalist->name,
            Finalist::class,
            $finalist->id,
            ['is_active' => $finalist->is_active]
        );

        return back()->with('status', $finalist->is_active ? 'Finalist activated.' : 'Finalist deactivated.');
    }

    public function destroy(Finalist $finalist): RedirectResponse
    {
        if (Evaluation::where('finalist_id', $finalist->id)->exists()) {
            return back()->with('error', 'This finalist already has evaluations and cannot be deleted. Deactivate it instead.');
        }

        $code = $finalist->code;
        $finalist->delete();
        $this->audit->log('finalist.delete', 'Deleted finalist '.$code, Finalist::class, null, ['code' => $code]);

        return redirect()->route('admin.finalists.index')->with('status', 'Finalist deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Finalist $finalist = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'code' => ['nullable', 'string', 'max:20', 'unique:finalists,code'.($finalist ? ','.$finalist->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'project_title' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (blank($data['code'] ?? null)) {
            unset($data['code']);
            $data['code'] = $this->generateCode((int) $data['category_id']);
        }

        $data['display_order'] = $data['display_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function generateCode(int $categoryId): string
    {
        $category = Category::find($categoryId);
        $prefix = $category && str_contains((string) $category->slug, 'feasibility') ? 'FS' : 'IG';
        $number = Finalist::where('category_id', $categoryId)->count() + 1;

        do {
            $code = sprintf('%s-%03d', $prefix, $number);
            $number++;
        } while (Finalist::where('code', $code)->exists());

        return $code;
    }
}
