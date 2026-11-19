@extends('layouts.app')

@section('title', $category->name.' results - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('admin.results.index') }}" class="text-sm text-navy-700 hover:underline">&larr; All results</a>
            <h1 class="mt-2 text-2xl font-semibold text-navy-900">{{ $category->name }} &middot; Results</h1>
            <p class="text-sm text-slate-500">{{ $submitted }} of {{ $required }} required evaluations submitted ({{ $percentage }}%).</p>
        </div>
        <div>
            @if ($release)
                @include('partials.status-badge', ['state' => 'released'])
            @elseif ($complete)
                @include('partials.status-badge', ['state' => 'pending'])
            @else
                @include('partials.status-badge', ['state' => 'locked_results'])
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-navy-900">Completion</h2>
            <span class="text-sm font-semibold text-navy-900">{{ $percentage }}%</span>
        </div>
        <div class="mt-2">@include('partials.progress-bar', ['percent' => $percentage]) </div>
        <p class="mt-3 text-sm text-slate-600">Remaining evaluations: <span class="font-semibold text-navy-900">{{ $remaining }}</span></p>
    </div>

    @if ($release && $release->needsReview())
        <div class="mt-4 rounded-lg border-2 border-amber-300 bg-amber-50 p-5" role="alert">
            <h2 class="text-base font-semibold text-amber-900">This released result has to be reviewed</h2>
            <p class="mt-1 text-sm text-amber-800">
                A correction or a reset changed an evaluation of this category after the results were released
                ({{ \App\Support\DateTimeDisplay::format($release->released_at) }}). The ranking below reflects the
                corrected evaluations. Confirm the review and republish the result.
            </p>
            <form method="POST" action="{{ route('admin.results.republish', $category) }}" class="mt-3 max-w-xl space-y-2"
                  onsubmit="return confirm('Republish the official result of {{ $category->name }}? This action is recorded in the audit log.');">
                @csrf
                <label for="republish-note" class="block text-xs font-semibold text-amber-900">Review note <span class="text-rose-600">*</span></label>
                <input id="republish-note" type="text" name="note" required minlength="10" maxlength="500"
                       placeholder="What was reviewed and why the result is republished"
                       class="block w-full rounded-md border border-amber-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    Confirm review and republish
                </button>
            </form>
        </div>
    @endif

    @if (! $complete)
        <div class="mt-4 rounded-lg border-2 border-rose-200 bg-rose-50 p-6 text-center">
            <h2 class="text-lg font-semibold text-rose-900">Results Locked</h2>
            <p class="mt-2 text-sm text-rose-800">
                The ranking for {{ $category->name }} is not available yet. Every required evaluation of every active finalist must be submitted by all active judges.
            </p>
            <p class="mt-2 text-xs text-rose-700">No ranking data is sent to the browser until the results are released.</p>
        </div>
    @elseif (! $release)
        <div class="mt-4 rounded-lg border-2 border-amber-200 bg-amber-50 p-6 text-center">
            <h2 class="text-lg font-semibold text-amber-900">All evaluations are complete - ready to release</h2>
            <p class="mt-2 text-sm text-amber-800">The ranking is calculated but stays hidden until you release the results for this category.</p>
            <form method="POST" action="{{ route('admin.results.release', $category) }}" class="mx-auto mt-4 max-w-md space-y-2"
                  onsubmit="return confirm('Release the results of {{ $category->name }}? This action is recorded in the audit log.');">
                @csrf
                <label for="note" class="sr-only">Release note</label>
                <input id="note" type="text" name="note" placeholder="Optional note for the audit log"
                       class="block w-full rounded-md border border-amber-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                <button type="submit" class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Release Results</button>
            </form>
        </div>
    @else
        @if ($ranking)
            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-navy-900">Final ranking</h2>
                    <p class="text-xs text-slate-500">
                        Average of the weighted totals of the {{ $ranking['judge_count'] }} designated judges.
                        Ties share the same rank (standard competition ranking) and are flagged for review.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Rank</th>
                                <th class="px-4 py-3 text-left font-medium">ID</th>
                                <th class="px-4 py-3 text-left font-medium">Finalist</th>
                                <th class="px-4 py-3 text-left font-medium">Division</th>
                                <th class="px-4 py-3 text-left font-medium">Project title</th>
                                @foreach ($judges as $judge)
                                    <th class="px-4 py-3 text-left font-medium">{{ $judge->name }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-left font-medium">Judges</th>
                                <th class="px-4 py-3 text-left font-medium">Average</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($ranking['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-3 font-bold text-navy-900">
                                        {{ $row['rank'] }}
                                        @if ($row['tied'])
                                            <span class="ml-1 rounded bg-gold-100 px-1.5 py-0.5 text-[10px] font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">Tie</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-navy-700">{{ $row['finalist']->code }}</td>
                                    <td class="px-4 py-3 font-medium text-navy-900">{{ $row['finalist']->name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $row['finalist']->department }}</td>
                                    <td class="max-w-sm px-4 py-3 text-slate-700">{{ $row['finalist']->project_title }}</td>
                                    @foreach ($judges as $judge)
                                        <td class="px-4 py-3 text-slate-700">
                                            {{ isset($row['judge_totals'][$judge->id]) && $row['judge_totals'][$judge->id] !== null ? number_format($row['judge_totals'][$judge->id], 2) : '-' }}
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-slate-700">{{ $row['submitted_count'] }}/{{ $row['required_count'] }}</td>
                                    <td class="px-4 py-3 font-semibold text-navy-900">{{ $row['average'] !== null ? number_format($row['average'], 2) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
