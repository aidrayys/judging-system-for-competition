@extends('layouts.app')

@section('title', 'Evaluation submitted - Judging System for Competition')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="rounded-lg border-2 border-emerald-200 bg-emerald-50 p-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-xl font-semibold text-emerald-900">Evaluation submitted</h1>
            <p class="mt-2 text-sm text-emerald-800">
                Your evaluation for {{ $finalist->code }} &mdash; {{ $finalist->name }} ({{ $category->name }}) has been recorded.
            </p>
            @if ($evaluation && $evaluation->submitted_at)
                <p class="mt-1 text-xs text-emerald-700">Submitted at {{ \App\Support\DateTimeDisplay::format($evaluation->submitted_at) }}</p>
            @endif
        </div>

        <div class="mt-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-navy-900">Your scores</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Criterion</th>
                            <th class="px-3 py-2 text-left font-medium">Weight</th>
                            <th class="px-3 py-2 text-left font-medium">Score</th>
                            <th class="px-3 py-2 text-left font-medium">Weighted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($criteria as $criterion)
                            @php
                                $score = $scores[$criterion->id] ?? null;
                                $contribution = $score ? round($score / \App\Services\ScoringService::MAX_SCORE * (float) $criterion->weight, 2) : null;
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-slate-800">{{ $criterion->name }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ number_format($criterion->weight, 2) }}%</td>
                                <td class="px-3 py-2 font-semibold text-navy-900">{{ $score ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $contribution !== null ? number_format($contribution, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td class="px-3 py-2 font-semibold text-navy-900" colspan="3">Total weighted score</td>
                            <td class="px-3 py-2 font-bold text-navy-900">{{ $evaluation && $evaluation->total_score !== null ? number_format($evaluation->total_score, 2) : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <a href="{{ route('judge.dashboard') }}" class="mt-5 inline-block rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">
                Back to my judging
            </a>
        </div>
    </div>
@endsection
