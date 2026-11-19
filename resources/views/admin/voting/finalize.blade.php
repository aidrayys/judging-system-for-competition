@extends('layouts.app')
@section('title','Finalize Favorite Idea Voting')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4">Finalize Favorite Idea Voting</h1>
    <p class="mb-4 text-sm">Rank 1, 2, and 3 are excluded from Favorite Idea award eligibility.</p>

    @if($tie)
        <div class="bg-amber-100 border border-amber-300 text-amber-800 p-4 rounded mb-4">
            There is a tie for the winning position. Please review manually.
        </div>
    @endif

    @if($winner)
        <div class="bg-emerald-50 border border-emerald-200 p-4 rounded mb-6">
            <h2 class="font-semibold">Selected Favorite Idea Winner</h2>
            <p>{{ $winner->code }} - {{ $winner->name }}</p>
            <p class="text-sm">Votes: {{ $winner->votes_count ?? 0 }}</p>
        </div>
    @else
        <div class="bg-rose-50 border border-rose-200 p-4 rounded mb-6">No eligible winner found.</div>
    @endif

    <table class="w-full text-left text-sm">
        <thead><tr><th>Rank</th><th>Code</th><th>Name</th><th>Votes</th><th>Excluded</th></tr></thead>
        <tbody>
            @foreach($candidates as $i => $f)
            <tr class="border-t {{ $winner && $winner->id===$f->id ? 'bg-emerald-50' : '' }}">
                <td>{{ $i+1 }}</td>
                <td>{{ $f->code }}</td>
                <td>{{ $f->name }}</td>
                <td>{{ $f->votes_count ?? 0 }}</td>
                <td>{{ $f->isTopThreeWinner() ? 'Rank '.$f->winner_rank : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
