@extends('layouts.app')

@section('title', 'Judging System for Competition - Innovation Management Platform')

@section('content')
    <div class="mx-auto max-w-3xl py-10 text-center">
        <img src="{{ asset('images/logo.svg') }}" alt="Judging System for Competition logo" class="mx-auto mb-4 h-14 w-14" width="56" height="56">
        <h1 class="text-3xl font-semibold text-navy-900">Judging System for Competition</h1>
        <p class="mt-2 text-slate-600">Innovation Challenge 2026.</p>
        <a href="{{ route('login') }}" class="mt-6 inline-block rounded-md bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy-800">Sign in</a>
    </div>
@endsection
