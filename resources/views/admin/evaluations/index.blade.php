@extends('layouts.app')

@section('title', 'Evaluations - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Evaluations</h1>
        <p class="text-sm text-slate-500">Individual judge scores and comments are visible to administrators only.</p>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <select name="judge" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">All judges</option>
            @foreach ($judges as $judge)
                <option value="{{ $judge->id }}" @selected((int) request('judge') === $judge->id)>{{ $judge->name }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="finalist" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">All finalists</option>
            @foreach ($finalists as $finalist)
                <option value="{{ $finalist->id }}" @selected((int) request('finalist') === $finalist->id)>
                    {{ $finalist->code }} &middot; {{ $finalist->name }}
                </option>
            @endforeach
        </select>
        <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">Any status</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
            <option value="locked" @selected(request('status') === 'locked')>Locked</option>
        </select>
        <button type="submit" class="rounded-md bg-navy-800 px-4 py-2 text-sm font-medium text-white hover:bg-navy-700">Filter</button>
        <a href="{{ route('admin.evaluations.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Judge</th>
                        <th class="px-4 py-3 text-left font-medium">Category</th>
                        <th class="px-4 py-3 text-left font-medium">Finalist</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Total</th>
                        <th class="px-4 py-3 text-left font-medium">Submitted at</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($evaluations as $evaluation)
                        <tr>
                            <td class="px-4 py-3 font-medium text-navy-900">{{ $evaluation->judge->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $evaluation->category->name }}</td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-navy-700">{{ $evaluation->finalist->code }}</span>
                                <span class="block text-slate-700">{{ $evaluation->finalist->name }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @include('partials.status-badge', ['state' => $evaluation->status === 'submitted' ? 'submitted' : ($evaluation->status === 'locked' ? 'locked' : 'draft')])
                            </td>
                            <td class="px-4 py-3 font-semibold text-navy-900">{{ $evaluation->total_score !== null ? number_format($evaluation->total_score, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ \App\Support\DateTimeDisplay::formatShort($evaluation->submitted_at) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.evaluations.show', $evaluation) }}" class="text-xs font-medium text-navy-700 hover:underline">View</a>
                                <span class="mx-1 text-slate-300">|</span>
                                <a href="{{ route('admin.evaluations.show', $evaluation) }}#evaluation-management" class="text-xs font-medium text-navy-700 hover:underline">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">No evaluations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $evaluations->links() }}</div>
    </div>
@endsection
