@extends('layouts.app')

@section('title', 'Admin Dashboard - Judging System for Competition')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-navy-900">Admin Dashboard</h1>
            <p class="text-sm text-slate-500">Judging progress, rubric health and result release status.</p>
        </div>
        <div class="flex items-center gap-2">
            @include('partials.status-badge', ['state' => $judgingOpen ? 'active' : 'inactive'])
            <span class="text-sm text-slate-600">{{ $judgingOpen ? 'Judging period is Open' : 'Judging period is Closed' }}</span>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border {{ $judgingOpen ? 'border-emerald-200 bg-emerald-50' : 'border-amber-300 bg-amber-50' }} p-4">
        <div>
            <p class="text-sm font-semibold {{ $judgingOpen ? 'text-emerald-900' : 'text-amber-900' }}">
                Judging period: {{ $judgingOpen ? 'OPEN' : 'CLOSED' }}
            </p>
            <p class="mt-0.5 text-xs {{ $judgingOpen ? 'text-emerald-800' : 'text-amber-800' }}">
                @if ($judgingOpen)
                    Judges can currently score and submit evaluations. Opened {{ $openedAt ?: 'earlier' }}.
                @else
                    Judges can review their evaluations but cannot save or submit. Please contact the administrator if a judge reports this.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($judgingOpen)
                <form method="POST" action="{{ route('admin.settings.close') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                            onclick="return confirm('Close the judging period? Judges will no longer be able to save or submit evaluations.');">
                        Close judging period
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.settings.open') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Open judging period
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.settings.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Settings</a>
        </div>
    </div>

    @php
        $votingStatusColors = [
            'Not Open' => 'bg-slate-100 text-slate-700',
            'Open' => 'bg-emerald-100 text-emerald-700',
            'Closed' => 'bg-rose-100 text-rose-700',
            'Final' => 'bg-gold-100 text-gold-700',
        ];
    @endphp

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-navy-900">Favorite Idea Voting</h2>
                <p class="text-xs text-slate-500">Public voting summary. Full details and settings are under the Voting menu.</p>
            </div>
            <a href="{{ route('admin.voting.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Manage Voting</a>
        </div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</p>
                <span class="mt-2 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $votingStatusColors[$votingStatus] ?? 'bg-slate-100' }}">{{ $votingStatus }}</span>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Registered codes</p>
                <p class="mt-2 text-2xl font-semibold text-navy-900">{{ $votingTotalVoters }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Voted</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ $votingVoted }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total votes</p>
                <p class="mt-2 text-2xl font-semibold text-navy-900">{{ $votingTotalVotes }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active finalists</p>
            <p class="mt-2 text-3xl font-semibold text-navy-900">{{ $activeFinalists }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Required evaluations</p>
            <p class="mt-2 text-3xl font-semibold text-navy-900">{{ $requiredTotal }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Submitted</p>
            <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ $submittedTotal }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Remaining</p>
            <p class="mt-2 text-3xl font-semibold text-amber-700">{{ $remainingTotal }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-navy-900">Overall completion</h2>
            <span class="text-sm font-semibold text-navy-900">{{ $overallPercentage }}%</span>
        </div>
        <div class="mt-2">
            @include('partials.progress-bar', ['percent' => $overallPercentage])
        </div>
    </div>

    {{-- ================= Live provisional ranking (admin only) ================= --}}
    <div class="mt-6" id="provisional-ranking">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-navy-900">Live provisional ranking</h2>
                <p class="text-xs text-slate-500">
                    Provisional averages calculated on the server from the valid submitted evaluations of the five
                    designated judges ({{ $judges->pluck('name')->join(', ') }}). Drafts, autosaved drafts and
                    incomplete evaluations are excluded. These figures are <span class="font-semibold text-gold-700">not</span>
                    an official ranking: the official result is published only after every required evaluation is
                    complete and the results are released.
                </p>
            </div>
            <span class="rounded-full bg-gold-100 px-3 py-1 text-xs font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">
                Provisional &mdash; admin only
            </span>
        </div>

        <div class="grid gap-4">
            @foreach ($categories as $category)
                @php $provisional = $provisionalResults[$category->id] ?? null; @endphp
                @if ($provisional)
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-navy-900">{{ $category->name }}</h3>
                                <p class="text-xs text-slate-500">
                                    {{ $provisional['finalist_count'] }} active finalists &middot;
                                    {{ $provisional['fully_evaluated'] }} fully evaluated by all {{ $provisional['judge_count'] }} judges &middot;
                                    {{ $provisional['submitted'] }}/{{ $provisional['required'] }} valid evaluations submitted
                                </p>
                            </div>
                            <div class="text-right">
                                @if ($provisional['released'])
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-300">
                                        Provisional view &mdash; official results released
                                    </span>
                                @elseif ($provisional['complete'])
                                    <span class="inline-flex items-center rounded-full bg-gold-100 px-2.5 py-0.5 text-xs font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">
                                        Provisional &mdash; all evaluations in, official release pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gold-100 px-2.5 py-0.5 text-xs font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">
                                        Provisional &mdash; judging in progress
                                    </span>
                                @endif
                                <p class="mt-1 text-xs font-medium text-slate-600">Completion {{ $provisional['percentage'] }}%</p>
                            </div>
                        </div>

                        <div class="px-5 pt-4">
                            @include('partials.progress-bar', ['percent' => $provisional['percentage']])
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium">Provisional rank</th>
                                        <th class="px-4 py-3 text-left font-medium">Code</th>
                                        <th class="px-4 py-3 text-left font-medium">Finalist</th>
                                        <th class="px-4 py-3 text-left font-medium">Division</th>
                                        <th class="px-4 py-3 text-left font-medium">Project title</th>
                                        <th class="px-4 py-3 text-right font-medium">Avg score</th>
                                        <th class="px-4 py-3 text-center font-medium">Judges</th>
                                        <th class="px-4 py-3 text-left font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($provisional['rows'] as $row)
                                        <tr class="{{ ($row['rank'] === 1) ? 'bg-gold-100/40' : '' }}">
                                            <td class="px-4 py-3">
                                                @if ($row['rank'] !== null)
                                                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-navy-900 px-2 text-xs font-bold text-white">
                                                        {{ $row['rank'] }}
                                                    </span>
                                                    @if ($row['tied'])
                                                        <span class="ml-1 rounded bg-gold-100 px-1.5 py-0.5 text-[10px] font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">Tie</span>
                                                    @endif
                                                @else
                                                    <span class="text-slate-400">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-mono text-xs text-navy-700">{{ $row['finalist']->code }}</td>
                                            <td class="px-4 py-3 font-medium text-navy-900">{{ $row['finalist']->name }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $row['finalist']->department }}</td>
                                            <td class="max-w-xs px-4 py-3 text-slate-700">{{ $row['finalist']->project_title }}</td>
                                            <td class="px-4 py-3 text-right">
                                                @if ($row['average'] !== null)
                                                    <span class="font-semibold {{ $row['rank'] === 1 ? 'text-navy-900' : 'text-navy-700' }}">
                                                        {{ number_format($row['average'], 2) }}
                                                    </span>
                                                    <div class="mt-1 ml-auto h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                                        <div class="h-full rounded-full {{ $row['rank'] === 1 ? 'bg-gold-500' : 'bg-navy-500' }}"
                                                             style="width: {{ max(0, min(100, (float) $row['average'])) }}%"></div>
                                                    </div>
                                                @else
                                                    <span class="text-slate-400">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center text-xs font-semibold text-navy-700">
                                                {{ $row['submitted_count'] }}/{{ $row['required_count'] }}
                                            </td>
                                            <td class="px-4 py-3">
                                                @include('partials.status-badge', ['state' => $row['status']])
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">
                                                No active finalists in this category.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                            Provisional averages use only submitted, complete evaluations. A finalist without any valid
                            submission has no score yet and is shown as &mdash; with 0/{{ $provisional['judge_count'] }}.
                        </p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ================= Competition Results (top three) ================= --}}
    <div class="mt-6" id="competition-results">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-navy-900">Competition Results</h2>
                <p class="text-xs text-slate-500">
                    Top three per category, calculated on the server from the submitted evaluations of the
                    five designated judges ({{ $judges->pluck('name')->join(', ') }}).
                    Ties share a rank and are flagged for administrator review.
                </p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($categories as $category)
                @php $result = $competitionResults[$category->id]; @endphp
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-navy-900">{{ $category->name }}</h3>
                            <p class="text-xs text-slate-500">
                                {{ $result['finalists_complete'] }} of {{ $result['finalists_total'] }} finalists fully evaluated
                                &middot; {{ $result['submitted'] }}/{{ $result['required'] }} evaluations submitted
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($result['released'])
                                @include('partials.status-badge', ['state' => 'released'])
                            @elseif ($result['complete'])
                                @include('partials.status-badge', ['state' => 'pending'])
                            @else
                                @include('partials.status-badge', ['state' => 'in_progress'])
                            @endif
                        </div>
                    </div>

                    @if (! $result['complete'])
                        {{-- Not every finalist is fully evaluated: progress only, no ranking. --}}
                        <div class="px-5 py-5">
                            <p class="text-sm font-semibold text-amber-800">Status: In Progress &mdash; ranking not yet available</p>
                            <p class="mt-1 text-sm text-slate-600">
                                The official ranking is calculated once all {{ $result['finalists_total'] }} finalists of this category
                                have a complete evaluation from every designated judge.
                                No provisional leader is published in the meantime.
                            </p>
                            <div class="mt-3">
                                @include('partials.progress-bar', ['percent' => $result['percentage']])
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                Evaluation progress: {{ $result['percentage'] }}%
                                ({{ $result['submitted'] }} of {{ $result['required'] }} required evaluations submitted).
                            </p>
                            <a href="{{ route('admin.results.show', $category) }}" class="mt-4 inline-block rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Open result page
                            </a>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium">Rank</th>
                                        <th class="px-4 py-2 text-left font-medium">ID</th>
                                        <th class="px-4 py-2 text-left font-medium">Finalist</th>
                                        <th class="px-4 py-2 text-left font-medium">Division</th>
                                        <th class="px-4 py-2 text-left font-medium">Project title</th>
                                        <th class="px-4 py-2 text-right font-medium">Average</th>
                                        <th class="px-4 py-2 text-center font-medium">Judges</th>
                                        <th class="px-4 py-2 text-left font-medium">Result status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($result['rows'] as $row)
                                        <tr class="{{ $row['rank'] <= 3 ? 'bg-gold-100/40' : '' }}">
                                            <td class="px-4 py-2 font-bold text-navy-900">
                                                {{ $row['rank'] }}
                                                @if ($row['tied'])
                                                    <span class="ml-1 rounded bg-gold-100 px-1.5 py-0.5 text-[10px] font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">Tie</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 font-mono text-xs text-navy-700">{{ $row['finalist']->code }}</td>
                                            <td class="px-4 py-2 font-medium text-navy-900">{{ $row['finalist']->name }}</td>
                                            <td class="px-4 py-2 text-slate-600">{{ $row['finalist']->department }}</td>
                                            <td class="max-w-xs px-4 py-2 text-slate-700">{{ $row['finalist']->project_title }}</td>
                                            <td class="px-4 py-2 text-right font-semibold text-navy-900">{{ number_format($row['average'], 2) }}</td>
                                            <td class="px-4 py-2 text-center text-slate-700">{{ $row['submitted_count'] }}/{{ $row['required_count'] }}</td>
                                            <td class="px-4 py-2">
                                                @if ($result['released'])
                                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-300">Official</span>
                                                @else
                                                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300">Awaiting release</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">No ranked finalist available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-slate-200 px-5 py-4">
                            @if ($result['eligible_count'] > count($result['rows']))
                                <p class="mb-2 text-xs text-slate-500">
                                    {{ count($result['rows']) }} of {{ $result['eligible_count'] }} ranked finalists are shown
                                    (ranks 1&ndash;3). Tied finalists share a rank and are all listed.
                                </p>
                            @endif
                            @if ($result['ties'])
                                <p class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                    Tied positions detected. No tie-break rule is configured, so the tie is flagged for
                                    administrator review. Standard competition ranking is used (equal averages share a rank).
                                </p>
                            @endif

                            <div class="flex flex-wrap items-center gap-3">
                                @if (! $result['released'])
                                    <form method="POST" action="{{ route('admin.results.release', $category) }}"
                                          onsubmit="return confirm('Release the official results of {{ $category->name }}? This action is recorded in the audit log.');">
                                        @csrf
                                        <button type="submit" class="rounded-md bg-gold-500 px-4 py-2 text-sm font-bold text-navy-900 hover:bg-gold-400">
                                            Release Results
                                        </button>
                                    </form>
                                    <span class="text-xs text-slate-500">The ranking is visible to administrators only until it is released.</span>
                                @else
                                    <span class="text-xs text-slate-500">
                                        Released {{ \App\Support\DateTimeDisplay::formatShort($result['released_at']) }}.
                                    </span>
                                @endif
                                <a href="{{ route('admin.results.show', $category) }}" class="text-xs font-medium text-navy-700 hover:underline">Full ranking</a>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach ($categories as $category)
            @php $stat = $categoryStats[$category->id]; @endphp
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-navy-900">{{ $category->name }}</h2>
                        <p class="text-xs text-slate-500">{{ $category->activeFinalists()->count() }} active finalists</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (! $stat['weights_valid'])
                            <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-300">Weights {{ $stat['weight_total'] }}%</span>
                        @endif
                        @if ($stat['released'])
                            @include('partials.status-badge', ['state' => 'released'])
                        @elseif ($stat['complete'])
                            @include('partials.status-badge', ['state' => 'pending'])
                        @else
                            @include('partials.status-badge', ['state' => 'locked_results'])
                        @endif
                    </div>
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <span>{{ $stat['submitted'] }} of {{ $stat['required'] }} evaluations submitted</span>
                        <span>{{ $stat['percentage'] }}%</span>
                    </div>
                    <div class="mt-1">
                        @include('partials.progress-bar', ['percent' => $stat['percentage']])
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <a href="{{ route('admin.results.show', $category) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View results</a>
                    <a href="{{ route('admin.evaluations.index', ['category' => $category->id]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Evaluations</a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-navy-900">Judge progress</h2>
            <p class="text-xs text-slate-500">Completed evaluations per judge and category. Detailed scores and comments stay restricted to administrators.</p>
        </div>        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Judge</th>
                        @foreach ($categories as $category)
                            <th class="px-4 py-3 text-left font-medium">{{ $category->name }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-left font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($judges as $judge)
                        @php
                            $judgeSubmitted = 0;
                            $judgeTotal = 0;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-navy-900">{{ $judge->name }}<span class="block text-xs font-normal text-slate-500">&#64;{{ $judge->username }}</span></td>
                            @foreach ($categories as $category)
                                @php
                                    $progress = $judgeProgress[$judge->id][$category->id];
                                    $judgeSubmitted += $progress['submitted'];
                                    $judgeTotal += $progress['total'];
                                    $percent = $progress['total'] > 0 ? round($progress['submitted'] / $progress['total'] * 100) : 0;
                                @endphp
                                <td class="px-4 py-3">
                                    <div class="w-40">
                                        <div class="flex justify-between text-xs text-slate-600">
                                            <span>{{ $progress['submitted'] }}/{{ $progress['total'] }}</span>
                                            <span>{{ $percent }}%</span>
                                        </div>
                                        <div class="mt-1">@include('partials.progress-bar', ['percent' => $percent])</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-4 py-3 font-medium text-navy-900">{{ $judgeSubmitted }}/{{ $judgeTotal }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $categories->count() + 2 }}" class="px-4 py-6 text-center text-slate-500">No active judge accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
