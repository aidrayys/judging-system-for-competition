@extends('layouts.app')
@section('title','Favorite Idea Voting - Judging System for Competition')
@section('content')
<div class="max-w-md mx-auto mt-10 px-4">
    <h1 class="text-2xl font-bold text-center mb-2">Favorite Idea Voting</h1>
    <p class="text-center mb-6">Enter your 5-digit voting code to begin.</p>
    <form method="POST" action="{{ route('voting.public.verify') }}" class="bg-white p-6 rounded-lg shadow">
        @csrf
        <label class="block text-sm font-medium mb-1">Voting Code</label>
        <input type="text" name="kode_unik" inputmode="numeric" pattern="[1-9]\d{4}" maxlength="5" required class="w-full border rounded p-2 mb-4" autocomplete="off">
        @error('kode_unik')<p class="text-rose-600 text-sm mb-2">{{ $message }}</p>@enderror
        <button type="submit" class="w-full bg-navy-900 text-white py-2 rounded">Verifikasi</button>
    </form>
</div>
@endsection
