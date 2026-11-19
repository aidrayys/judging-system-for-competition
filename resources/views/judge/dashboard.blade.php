@extends('layouts.app')

@section('title', 'Judge Portal - Judging System for Competition')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-navy-900">Welcome, {{ $judge->name }}</h1>
            <p class="text-sm text-slate-500">Please evaluate every active finalist in both categories. Your draft is saved automatically.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Your progress</p>
            <p class="text-lg font-semibold text-navy-900">{{ $totalSubmitted }} / {{ $totalFinalists }} submitted</p>
            <div class="mt-1 w-48">
                @include('partials.progress-bar', ['percent' => $totalFinalists > 0 ? round($totalSubmitted / $totalFinalists * 100) : 0])
            </div>
        </div>
    </div>

    @unless ($judgingOpen)
        <div class="mb-6 rounded-lg border-2 border-amber-300 bg-amber-50 p-4" role="alert">
            <p class="font-semibold text-amber-900">The judging period is currently closed.</p>
            <p class="mt-1 text-sm text-amber-800">
                You can still review your evaluations, but saving and submitting are disabled until the
                administrator reopens the judging period. Please contact the administrator if you believe
                this is an error.
            </p>
        </div>
    @endunless

    @foreach ($blocks as $block)
        <div class="mb-6 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-navy-900">{{ $block['category']->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $block['category']->description }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-navy-900">{{ $block['submitted'] }} / {{ $block['total'] }} submitted</p>
                    <div class="mt-1 w-40">
                        @include('partials.progress-bar', ['percent' => $block['total'] > 0 ? round($block['submitted'] / $block['total'] * 100) : 0])
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Code</th>
                            <th class="px-4 py-3 text-left font-medium">Finalist</th>
                            <th class="px-4 py-3 text-left font-medium">Department</th>
                            <th class="px-4 py-3 text-left font-medium">Project title</th>
                            <th class="px-4 py-3 text-left font-medium">Presentation</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($block['rows'] as $row)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-navy-700">{{ $row['finalist']->code }}</td>
                                <td class="px-4 py-3 font-medium text-navy-900">{{ $row['finalist']->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['finalist']->department }}</td>
                                <td class="max-w-md px-4 py-3 text-slate-700">{{ $row['finalist']->project_title }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['finalist']->hasPresentation())
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Available
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">Not uploaded</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @include('partials.status-badge', ['state' => $row['state']])
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('judge.evaluate', [$block['category'], $row['finalist']]) }}"
                                       class="text-xs font-semibold text-navy-700 hover:underline">
                                        {{ $row['state'] === 'submitted' ? 'View' : ($row['state'] === 'draft' ? 'Continue' : 'Evaluate') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-500">No finalists in this category.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
