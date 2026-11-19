@extends('layouts.app')

@section('title', 'Rubric - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Judging Rubric</h1>
        <p class="text-sm text-slate-500">
            Both categories use the same six criteria and weights. The active criteria of a category must total exactly 100% before the judging period can be opened.
        </p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($categories as $cat)
            <a href="{{ route('admin.criteria.index', ['category' => $cat->id]) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium {{ $category && $cat->id === $category->id ? 'bg-navy-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50' }}">
                {{ $cat->name }}
            </a>
        @endforeach
    </div>

    @if ($category)
        <div class="mb-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-navy-900">Category details</h2>
            <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-3 grid gap-4 sm:grid-cols-3">
                @csrf
                @method('PUT')
                <div>
                    <label for="cat_name" class="block text-xs font-semibold text-slate-700">Name</label>
                    <input id="cat_name" type="text" name="name" value="{{ old('name', $category->name) }}" required
                           class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                </div>
                <div class="sm:col-span-1">
                    <label for="cat_description" class="block text-xs font-semibold text-slate-700">Description</label>
                    <input id="cat_description" type="text" name="description" value="{{ old('description', $category->description) }}"
                           class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                </div>
                <div class="flex items-end gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input id="cat_active" type="checkbox" value="1" name="is_active" @checked($category->is_active)
                           class="h-4 w-4 rounded border-slate-300 text-navy-600 focus:ring-navy-500">
                    <label for="cat_active" class="text-xs text-slate-700">Active</label>
                    <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-navy-900">{{ $category->name }} criteria</h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-600">Active weight total:</span>
                    <span class="rounded px-2 py-0.5 text-sm font-semibold {{ abs($weightTotal - 100) < 0.001 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">{{ number_format($weightTotal, 2) }}%</span>
                </div>
            </div>

            @if ($locked)
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">The rubric is locked while the judging period is open.</p>
                    <p class="mt-1">Close the judging period from <a class="underline" href="{{ route('admin.settings.index') }}">Settings</a> to edit it, or use the audited reset procedure below.</p>
                </div>
            @else
                <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                    The rubric can be edited. The total weight of the active criteria must stay exactly 100%.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.criteria.update', $category) }}" class="mt-4 space-y-3">
                @csrf
                @method('PUT')

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">#</th>
                                <th class="px-3 py-2 text-left font-medium">Criterion &amp; explanation</th>
                                <th class="px-3 py-2 text-left font-medium">Weight (%)</th>
                                <th class="px-3 py-2 text-left font-medium">Order</th>
                                <th class="px-3 py-2 text-left font-medium">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($criteria as $criterion)
                                <tr>
                                    <td class="px-3 py-2 text-slate-500">{{ $criterion->display_order }}</td>
                                    <td class="px-3 py-2">
                                        <input type="hidden" name="criteria[{{ $criterion->id }}][id]" value="{{ $criterion->id }}">
                                        <label for="name-{{ $criterion->id }}" class="sr-only">Criterion name</label>
                                        <input id="name-{{ $criterion->id }}" type="text" name="criteria[{{ $criterion->id }}][name]" value="{{ old('criteria.'.$criterion->id.'.name', $criterion->name) }}" required
                                               class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                                        <label for="description-{{ $criterion->id }}" class="mt-1 block text-[11px] text-slate-500">Explanation shown to judges</label>
                                        <textarea id="description-{{ $criterion->id }}" name="criteria[{{ $criterion->id }}][description]" rows="2"
                                                  class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-600 focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">{{ old('criteria.'.$criterion->id.'.description', $criterion->description) }}</textarea>
                                    </td>
                                    <td class="px-3 py-2">
                                        <label for="weight-{{ $criterion->id }}" class="sr-only">Weight</label>
                                        <input id="weight-{{ $criterion->id }}" type="number" step="0.01" min="0" max="100" name="criteria[{{ $criterion->id }}][weight]" value="{{ old('criteria.'.$criterion->id.'.weight', $criterion->weight) }}" required
                                               class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                                    </td>
                                    <td class="px-3 py-2">
                                        <label for="order-{{ $criterion->id }}" class="sr-only">Display order</label>
                                        <input id="order-{{ $criterion->id }}" type="number" min="0" name="criteria[{{ $criterion->id }}][display_order]" value="{{ old('criteria.'.$criterion->id.'.display_order', $criterion->display_order) }}" required
                                               class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="hidden" name="criteria[{{ $criterion->id }}][is_active]" value="0">
                                        <label for="active-{{ $criterion->id }}" class="sr-only">Active</label>
                                        <input id="active-{{ $criterion->id }}" type="checkbox" value="1" name="criteria[{{ $criterion->id }}][is_active]" @checked(old('criteria.'.$criterion->id.'.is_active', $criterion->is_active))
                                               class="h-4 w-4 rounded border-slate-300 text-navy-600 focus:ring-navy-500">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">Save rubric</button>
            </form>
        </div>

        @if ($locked)
            <div class="mt-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-navy-900">Audited rubric reset</h3>
                <p class="mt-1 text-sm text-slate-500">Use this only when the rubric must be corrected while judging is running. The reason is recorded permanently in the audit log.</p>
                <form method="POST" action="{{ route('admin.criteria.reset', $category) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="flex-1 min-w-60">
                        <label for="reason" class="block text-sm font-medium text-slate-700">Reason for reset <span class="text-rose-600">*</span></label>
                        <textarea id="reason" name="reason" rows="2" required minlength="10"
                                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500"></textarea>
                    </div>
                    <button type="submit" class="rounded-md border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100">Authorize reset</button>
                </form>
            </div>
        @endif
    @endif
@endsection
