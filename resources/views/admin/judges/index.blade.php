@extends('layouts.app')

@section('title', 'Judges - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Judge Accounts</h1>
        <p class="text-sm text-slate-500">Manage judge logins and reset passwords. Passwords are never displayed after they are set.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @forelse ($judges as $judge)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-navy-900">{{ $judge->name }}</p>
                            <p class="text-sm text-slate-500">Username: <span class="font-mono">{{ $judge->username }}</span></p>
                            <p class="text-xs text-slate-400">
                                Last signed in: {{ $judge->last_login_at ? \App\Support\DateTimeDisplay::formatShort($judge->last_login_at) : 'never' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @include('partials.status-badge', ['state' => $judge->is_active ? 'active' : 'inactive'])
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                        <form method="POST" action="{{ route('admin.judges.reset-password', $judge) }}" class="rounded-md bg-slate-50 p-3">
                            @csrf
                            <label for="password-{{ $judge->id }}" class="block text-xs font-semibold text-slate-700">Reset password</label>
                            <input id="password-{{ $judge->id }}" type="password" name="password" required minlength="8" autocomplete="new-password"
                                   class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                            <label for="password_confirmation-{{ $judge->id }}" class="sr-only">Confirm password</label>
                            <input id="password_confirmation-{{ $judge->id }}" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                            <button type="submit" class="mt-2 rounded-md bg-navy-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-navy-700">Reset password</button>
                        </form>

                        <div class="space-y-2">
                            <form method="POST" action="{{ route('admin.judges.toggle', $judge) }}">
                                @csrf
                                <button class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    {{ $judge->is_active ? 'Disable account' : 'Enable account' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.judges.update', $judge) }}" class="rounded-md bg-slate-50 p-3">
                                @csrf
                                @method('PUT')
                                <label for="name-{{ $judge->id }}" class="block text-xs font-semibold text-slate-700">Display name</label>
                                <input id="name-{{ $judge->id }}" type="text" name="name" value="{{ $judge->name }}" required
                                       class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                                <label for="username-{{ $judge->id }}" class="mt-2 block text-xs font-semibold text-slate-700">Username</label>
                                <input id="username-{{ $judge->id }}" type="text" name="username" value="{{ $judge->username }}" required
                                       class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                                <button type="submit" class="mt-2 rounded-md bg-navy-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-navy-700">Save account</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500">No judge accounts found.</div>
            @endforelse
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-navy-900">Add judge</h2>
                <form method="POST" action="{{ route('admin.judges.store') }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label for="new_name" class="block text-xs font-semibold text-slate-700">Display name</label>
                        <input id="new_name" type="text" name="name" required value="{{ old('name') }}"
                               class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                    </div>
                    <div>
                        <label for="new_username" class="block text-xs font-semibold text-slate-700">Username</label>
                        <input id="new_username" type="text" name="username" required value="{{ old('username') }}"
                               class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                    </div>
                    <div>
                        <label for="new_password" class="block text-xs font-semibold text-slate-700">Temporary password</label>
                        <input id="new_password" type="password" name="password" required minlength="8" autocomplete="new-password"
                               class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                    </div>
                    <div>
                        <label for="new_password_confirmation" class="block text-xs font-semibold text-slate-700">Confirm password</label>
                        <input id="new_password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                               class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-navy-900 px-3 py-2 text-xs font-semibold text-white hover:bg-navy-800">Create judge account</button>
                </form>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-navy-900">Administrators</h2>
                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                    @foreach ($admins as $admin)
                        <li>{{ $admin->name }} <span class="font-mono text-xs text-slate-500">&#64;{{ $admin->username }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
