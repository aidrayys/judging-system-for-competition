@extends('layouts.app')
@section('title','Manage Voting Codes - Judging System for Competition')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Manage Voting Codes</h1>
        <a href="{{ route('admin.voting.index') }}" class="text-sm text-navy-700 underline">← Back to Voting</a>
    </div>

    @if($period && $period->is_finalized)
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
        <p class="font-medium text-amber-800">Voting has been finalized.</p>
        <form method="POST" action="{{ route('admin.voting.unfinalize') }}" class="mt-2" onsubmit="return confirm('Unfinalize? The final result will be removed and voting can be changed again.');">
            @csrf
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="px-4 py-2 rounded bg-amber-600 text-white text-sm">Unfinalize</button>
        </form>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="font-semibold">Reset All Voting</h2>
                <p class="text-sm text-slate-500">Delete all ballots for testing purposes. All voting codes are kept.</p>
            </div>
            <button type="button" onclick="document.getElementById('resetAllModal').classList.remove('hidden')" class="px-4 py-2 rounded bg-rose-700 text-white text-sm">Reset All</button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-3">Voting Code List</h2>
        <div class="divide-y divide-slate-200">
            @forelse($voters as $voter)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                    <div class="font-mono font-bold text-navy-900">{{ $voter->kode_unik }}</div>
                    <div class="text-xs text-slate-500">
                        @if($voter->has_voted)
                            <span class="text-emerald-700 font-medium">Already Used</span>
                        @else
                            <span class="text-slate-500">Not Used Yet</span>
                        @endif
                    </div>
                </div>
                @if($voter->has_voted)
                <button type="button" onclick="openResetSingle('{{ $voter->kode_unik }}', '{{ route('admin.voting.voters.reset', $voter) }}')" class="px-3 py-1.5 rounded border border-rose-300 text-rose-700 text-sm hover:bg-rose-50">Reset</button>
                @endif
            </div>
            @empty
            <div class="py-6 text-center text-slate-500">No voting codes yet.</div>
            @endforelse
        </div>
        <div class="mt-4">
            {{ $voters->links() }}
        </div>
    </div>
</div>

{{-- Reset single modal --}}
<div id="resetSingleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <h3 class="text-lg font-bold mb-2">Reset Voting Code</h3>
        <p class="text-sm mb-4">Reset code <span id="resetSingleCode" class="font-bold"></span>? The test ballot of this code will be deleted.</p>
        <form id="resetSingleForm" method="POST">
            @csrf
            <input type="hidden" name="confirm" value="1">
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('resetSingleModal').classList.add('hidden')" class="flex-1 py-2 rounded border border-slate-300">Cancel</button>
                <button type="submit" class="flex-1 py-2 rounded bg-rose-700 text-white">Reset</button>
            </div>
        </form>
    </div>
</div>

{{-- Reset all modal --}}
<div id="resetAllModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <h3 class="text-lg font-bold mb-2 text-rose-700">Reset All Voting</h3>
        <p class="text-sm mb-4">This deletes ALL test ballots and makes every voting code available again. Finalist data, judge evaluations and accounts are not affected.</p>
        <p class="text-sm mb-2">Type <span class="font-bold">RESET</span> to continue:</p>
        <form method="POST" action="{{ route('admin.voting.reset-all') }}">
            @csrf
            <input type="text" name="confirmation" pattern="RESET" required placeholder="RESET" class="w-full border rounded p-2 mb-4 uppercase">
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('resetAllModal').classList.add('hidden')" class="flex-1 py-2 rounded border border-slate-300">Cancel</button>
                <button type="submit" class="flex-1 py-2 rounded bg-rose-700 text-white">Reset All</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetSingle(code, action) {
    document.getElementById('resetSingleCode').textContent = code;
    document.getElementById('resetSingleForm').action = action;
    document.getElementById('resetSingleModal').classList.remove('hidden');
}
</script>
@endsection
