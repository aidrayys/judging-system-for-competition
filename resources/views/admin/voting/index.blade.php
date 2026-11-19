@extends('layouts.app')
@section('title','Favorite Idea Voting Admin')
@section('head')
<meta http-equiv="refresh" content="30">
@endsection
@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4">Favorite Idea Voting</h1>

    @php
        $statusColors = [
            'Not Open' => 'bg-slate-100 text-slate-700',
            'Open' => 'bg-emerald-100 text-emerald-700',
            'Closed' => 'bg-rose-100 text-rose-700',
            'Final' => 'bg-gold-100 text-gold-700',
        ];
    @endphp

    <div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium">Status Voting:</span>
            <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $statusColors[$status] ?? 'bg-slate-100' }}">{{ $status }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.voting.toggle') }}" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 rounded text-sm {{ $period?->is_open ? 'bg-rose-700 text-white' : 'bg-emerald-700 text-white' }}">
                    {{ $period?->is_open ? 'Close Voting' : 'Open Voting' }}
                </button>
            </form>
            @if($period && ! $period->is_finalized && $status !== 'Open')
            <form method="POST" action="{{ route('admin.voting.finalize') }}" class="inline" onsubmit="return confirm('Finalize the voting result? It cannot be changed afterwards.');">
                @csrf
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="px-4 py-2 rounded text-sm bg-gold-600 text-white">Finalize Result</button>
            </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded shadow p-4"><div class="text-sm">Registered Codes</div><div class="text-xl font-bold">{{ $totalVoters }}</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-sm">Already Used</div><div class="text-xl font-bold">{{ $voted }}</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-sm">Not Used Yet</div><div class="text-xl font-bold">{{ $notVoted }}</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-sm">Partisipasi</div><div class="text-xl font-bold">{{ $participation }}%</div></div>
        <div class="bg-white rounded shadow p-4"><div class="text-sm">Total Votes Cast</div><div class="text-xl font-bold">{{ $totalVotes }}</div></div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h2 class="font-semibold mb-2">Voting Period ({{ config('app.display_timezone', 'UTC') }})</h2>
        <form method="POST" action="{{ route('admin.voting.period.store') }}" class="grid gap-4 md:grid-cols-5 items-end">
            @csrf
            <div>
                <label class="text-sm">Opens</label>
                <input type="datetime-local" name="opens_at" value="{{ $period?->opens_at?->timezone(config('app.display_timezone', 'UTC'))->format('Y-m-d\TH:i') }}" class="border rounded p-2 w-full" required>
            </div>
            <div>
                <label class="text-sm">Closes</label>
                <input type="datetime-local" name="closes_at" value="{{ $period?->closes_at?->timezone(config('app.display_timezone', 'UTC'))->format('Y-m-d\TH:i') }}" class="border rounded p-2 w-full" required>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_open" value="1" id="is_open" {{ $period?->is_open ? 'checked' : '' }}>
                <label for="is_open">Open</label>
            </div>
            <button type="submit" class="bg-navy-900 text-white rounded p-2">Save Period</button>
        </form>
    </div>

    @if($period && $period->is_finalized && $finalWinner)
    <div class="bg-gold-50 border border-gold-200 rounded-lg p-4 mb-6">
        <h2 class="font-bold text-lg mb-1">Final Favorite Idea Result</h2>
        <p class="mb-2">The winner is the finalist with the most votes that is not a top-3 winner of the main competition.</p>
        <div class="font-semibold">{{ $finalWinner->code }} - {{ $finalWinner->name }}</div>
        <div class="text-sm">Votes: {{ $finalWinner->votes_count ?? 0 }} ({{ $finalWinner->vote_percentage }}%)</div>
        @if($finalTie)
            <div class="mt-2 text-amber-700 font-medium">⚠️ There is a tie for the winner position. Please review manually.</div>
        @endif
    </div>
    @endif

    <div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="font-semibold">{{ ($period && $period->is_finalized) ? 'Final Favorite Idea Ranking' : 'Provisional Favorite Idea Ranking' }}</h2>
            @if($tieExists)
                <p class="text-sm text-amber-700 mt-1">⚠️ There is a tie in the ranking. The winner is not determined automatically.</p>
            @endif
        </div>
        <div class="flex items-center gap-4">
            <div class="text-sm text-slate-500 text-right">Total votes cast<br><span class="font-semibold text-navy-900 text-lg">{{ $totalVotes }}</span></div>
            <a href="{{ route('admin.voting.export') }}" class="bg-emerald-700 text-white rounded px-4 py-2 text-sm">Export CSV</a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="divide-y divide-slate-200">
            @forelse($rankings as $f)
            <div class="grid grid-cols-[72px_1fr_100px] gap-4 items-center py-4 {{ $f->is_tie ? 'bg-amber-50/50' : '' }}">
                <div class="flex justify-start">
                    <div class="w-12 h-12 rounded-full bg-navy-900 text-white flex items-center justify-center font-bold text-base">
                        {{ $f->rank }}
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="font-bold text-navy-900 text-base">{{ $f->name }}</div>
                    <div class="mt-1 text-sm text-slate-700 break-words whitespace-normal leading-relaxed">{{ $f->project_title }}</div>
                    @if($f->is_tie)
                        <div class="mt-1 text-xs text-amber-700 font-medium">Tie</div>
                    @endif
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-navy-900 leading-none">{{ $f->votes_count ?? 0 }}</div>
                    <div class="text-xs text-slate-500">vote</div>
                </div>
            </div>
            @empty
            <div class="py-6 text-center text-slate-500">No ranking data yet.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold mb-1">Manage Voting Codes</h2>
            <p class="text-sm text-slate-500">Reset voting codes that were used for testing.</p>
        </div>
        <a href="{{ route('admin.voting.voters') }}" class="px-4 py-2 rounded bg-navy-900 text-white text-sm">Manage Voting Codes</a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h2 class="font-semibold mb-2">Import Voter Codes (CSV with a "kode_unik" column)</h2>
        <form method="POST" action="{{ route('admin.voting.import-voters') }}" enctype="multipart/form-data" class="flex gap-2">
            @csrf
            <input type="file" name="csv" accept=".csv" required class="border rounded p-2 flex-1">
            <button type="submit" class="bg-navy-900 text-white rounded px-4">Import</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-2">Main Competition Winner Marking (excluded from Favorite Idea)</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th>Code</th><th>Name</th><th>Category</th><th>Rank</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($rankings as $f)
                    <tr class="border-t">
                        <td class="py-2">{{ $f->code }}</td>
                        <td>{{ $f->name }}</td>
                        <td>{{ $f->category->name }}</td>
                        <td>{{ $f->winner_rank ? 'Rank '.$f->winner_rank : '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.voting.set-winner', $f) }}" class="inline-flex gap-1">
                                @csrf
                                <select name="winner_rank" class="border rounded text-sm">
                                    <option value="">-</option>
                                    <option value="1" {{ $f->winner_rank==1?'selected':'' }}>1</option>
                                    <option value="2" {{ $f->winner_rank==2?'selected':'' }}>2</option>
                                    <option value="3" {{ $f->winner_rank==3?'selected':'' }}>3</option>
                                </select>
                                <button type="submit" class="text-xs bg-navy-900 text-white rounded px-2">Set</button>
                            </form>
                            @if($f->winner_rank)
                            <form method="POST" action="{{ route('admin.voting.clear-winner', $f) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-rose-600 underline">Delete</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

