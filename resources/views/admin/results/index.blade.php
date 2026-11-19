@extends('layouts.app')

@section('title', 'Results - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Results</h1>
        <p class="text-sm text-slate-500">
            Rankings stay locked until every required evaluation of a category has been submitted by all active judges, and the administrator releases the results.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($categories as $category)
            @php $stat = $status[$category->id]; @endphp
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="text-base font-semibold text-navy-900">{{ $category->name }}</h2>
                    @if ($stat['released'])
                        @include('partials.status-badge', ['state' => 'released'])
                    @elseif ($stat['complete'])
                        @include('partials.status-badge', ['state' => 'pending'])
                    @else
                        @include('partials.status-badge', ['state' => 'locked_results'])
                    @endif
                </div>

                <p class="mt-2 text-sm text-slate-600">{{ $stat['submitted'] }} of {{ $stat['required'] }} required evaluations submitted ({{ $stat['percentage'] }}%).</p>
                <div class="mt-2">@include('partials.progress-bar', ['percent' => $stat['percentage']]) </div>

                @if ($stat['released'] && $stat['released_at'])
                    <p class="mt-3 text-xs text-slate-500">Released on {{ \App\Support\DateTimeDisplay::formatShort($stat['released_at']) }}</p>
                @endif

                @if ($stat['review_required'])
                    <div class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-3">
                        <p class="text-xs font-semibold text-amber-900">The released result has to be reviewed.</p>
                        <p class="mt-1 text-xs text-amber-800">
                            A correction or a reset changed an evaluation of this category after the results were
                            released. Review the change and republish the result.
                        </p>
                        <a href="{{ route('admin.results.show', $category) }}" class="mt-2 inline-block text-xs font-semibold text-amber-900 underline">
                            Review and republish
                        </a>
                    </div>
                @endif

                <a href="{{ route('admin.results.show', $category) }}" class="mt-4 inline-block rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">
                    {{ $stat['released'] ? 'View ranking' : 'Open result page' }}
                </a>
            </div>
        @endforeach
    </div>
@endsection
