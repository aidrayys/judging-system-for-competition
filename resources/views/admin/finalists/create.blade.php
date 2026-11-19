@extends('layouts.app')

@section('title', 'Add finalist - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Add finalist</h1>
        <p class="text-sm text-slate-500">Create a new finalist record for one of the two categories.</p>
    </div>

    <form method="POST" action="{{ route('admin.finalists.store') }}" class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="code" class="block text-sm font-medium text-slate-700">Finalist code</label>
                <input id="code" name="code" type="text" value="{{ old('code') }}" placeholder="e.g. IG-011"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                <p class="mt-1 text-xs text-slate-500">Leave empty to generate automatically.</p>
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium text-slate-700">Category <span class="text-rose-600">*</span></label>
                <select id="category_id" name="category_id" required
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                    <option value="">Select a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Finalist name <span class="text-rose-600">*</span></label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>
            <div>
                <label for="department" class="block text-sm font-medium text-slate-700">Department <span class="text-rose-600">*</span></label>
                <input id="department" name="department" type="text" required value="{{ old('department') }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>
        </div>

        <div>
            <label for="project_title" class="block text-sm font-medium text-slate-700">Project title <span class="text-rose-600">*</span></label>
            <textarea id="project_title" name="project_title" rows="3" required
                      class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">{{ old('project_title') }}</textarea>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Idea description</label>
            <textarea id="description" name="description" rows="5" maxlength="5000"
                      class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">{{ old('description') }}</textarea>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="display_order" class="block text-sm font-medium text-slate-700">Display order</label>
                <input id="display_order" name="display_order" type="number" min="0" value="{{ old('display_order', 0) }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>
            <div class="flex items-end">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', true))
                       class="h-4 w-4 rounded border-slate-300 text-navy-600 focus:ring-navy-500">
                <label for="is_active" class="ml-2 text-sm text-slate-700">Active</label>
            </div>
        </div>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
            <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">Create finalist</button>
            <a href="{{ route('admin.finalists.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
