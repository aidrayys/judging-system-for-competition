@extends('layouts.app')

@section('title', 'Settings - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Judging Period &amp; Settings</h1>
        <p class="text-sm text-slate-500">Control when judges may score and submit. The period can only be opened when every category rubric totals exactly 100%.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-navy-900">Judging period</h2>
            <div class="mt-3 flex items-center gap-2">
                @include('partials.status-badge', ['state' => $judgingOpen ? 'active' : 'inactive'])
                <span class="text-sm text-slate-700">{{ $judgingOpen ? 'Open - judges can score and submit' : 'Closed - submissions are blocked' }}</span>
            </div>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Opened at</dt><dd class="text-slate-700">{{ $openedAt ?: '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Closed at</dt><dd class="text-slate-700">{{ $closedAt ?: '-' }}</dd></div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                @if (! $judgingOpen)
                    <form method="POST" action="{{ route('admin.settings.open') }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Open judging period</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.settings.close') }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Close judging period</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-navy-900">Rubric validation</h2>
            <p class="mt-1 text-sm text-slate-500">Active criteria weights per category (must be 100%):</p>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($categories as $category)
                    <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                        <span class="text-slate-700">{{ $category->name }}</span>
                        <span class="font-semibold {{ abs($weights[$category->id] - 100) < 0.001 ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($weights[$category->id], 2) }}%</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('admin.criteria.index') }}" class="mt-4 inline-block text-sm font-medium text-navy-700 hover:underline">Manage rubric &rarr;</a>
        </div>
    </div>
@endsection
