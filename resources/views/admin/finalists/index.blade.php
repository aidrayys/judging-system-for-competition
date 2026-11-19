@extends('layouts.app')

@section('title', 'Finalists - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-navy-900">Finalists</h1>
            <p class="text-sm text-slate-500">Manage finalist data, categories and activation status.</p>
        </div>
        <a href="{{ route('admin.finalists.create') }}" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">Add finalist</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, title, department or code"
               class="min-w-60 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
        <select name="category" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-md bg-navy-800 px-4 py-2 text-sm font-medium text-white hover:bg-navy-700">Filter</button>
        <a href="{{ route('admin.finalists.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Code</th>
                        <th class="px-4 py-3 text-left font-medium">Name</th>
                        <th class="px-4 py-3 text-left font-medium">Department</th>
                        <th class="px-4 py-3 text-left font-medium">Project title</th>
                        <th class="px-4 py-3 text-left font-medium">Category</th>
                        <th class="px-4 py-3 text-left font-medium">Order</th>
                        <th class="px-4 py-3 text-left font-medium">Presentation</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($finalists as $finalist)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-navy-800">{{ $finalist->code }}</td>
                            <td class="px-4 py-3 font-medium text-navy-900">{{ $finalist->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $finalist->department }}</td>
                            <td class="max-w-sm px-4 py-3 text-slate-700">{{ $finalist->project_title }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $finalist->category->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $finalist->display_order }}</td>
                            <td class="px-4 py-3">
                                @if ($finalist->hasPresentation())
                                    <span class="block max-w-48 truncate text-xs text-slate-700" title="{{ $finalist->presentation_original_name }}">{{ $finalist->presentation_original_name }}</span>
                                    <span class="text-xs text-slate-500">{{ $finalist->presentationSizeForHumans() ?? '' }}</span>
                                @else
                                    <span class="text-xs text-amber-700">Not uploaded</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @include('partials.status-badge', ['state' => $finalist->is_active ? 'active' : 'inactive'])
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.finalists.edit', $finalist) }}" class="text-xs font-medium text-navy-700 hover:underline">Edit</a>
                                <a href="{{ route('admin.presentations.index', ['q' => $finalist->code]) }}" class="ml-3 text-xs font-medium text-navy-700 hover:underline">Presentation</a>
                                <form method="POST" action="{{ route('admin.finalists.toggle', $finalist) }}" class="inline">
                                    @csrf
                                    <button class="ml-3 text-xs font-medium text-slate-600 hover:underline">{{ $finalist->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.finalists.destroy', $finalist) }}" class="inline"
                                      onsubmit="return confirm('Delete {{ $finalist->code }}? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-3 text-xs font-medium text-rose-700 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">No finalists found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $finalists->links() }}</div>
    </div>
@endsection
