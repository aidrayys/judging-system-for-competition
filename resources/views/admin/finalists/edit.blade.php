@extends('layouts.app')

@section('title', 'Edit finalist - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Edit finalist</h1>
        <p class="text-sm text-slate-500">{{ $finalist->code }} &middot; {{ $finalist->name }}</p>
    </div>

    <form method="POST" action="{{ route('admin.finalists.update', $finalist) }}" class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="code" class="block text-sm font-medium text-slate-700">Finalist code</label>
                <input id="code" name="code" type="text" value="{{ old('code', $finalist->code) }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium text-slate-700">Category <span class="text-rose-600">*</span></label>
                <select id="category_id" name="category_id" required
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $finalist->category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Finalist name <span class="text-rose-600">*</span></label>
                <input id="name" name="name" type="text" required value="{{ old('name', $finalist->name) }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>
            <div>
                <label for="department" class="block text-sm font-medium text-slate-700">Department <span class="text-rose-600">*</span></label>
                <input id="department" name="department" type="text" required value="{{ old('department', $finalist->department) }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>
        </div>

        <div>
            <label for="project_title" class="block text-sm font-medium text-slate-700">Project title <span class="text-rose-600">*</span></label>
            <textarea id="project_title" name="project_title" rows="3" required
                      class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">{{ old('project_title', $finalist->project_title) }}</textarea>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Idea description</label>
            <textarea id="description" name="description" rows="5" maxlength="5000"
                      class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">{{ old('description', $finalist->description) }}</textarea>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="display_order" class="block text-sm font-medium text-slate-700">Display order</label>
                <input id="display_order" name="display_order" type="number" min="0" value="{{ old('display_order', $finalist->display_order) }}"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            </div>
            <div class="flex items-end">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $finalist->is_active))
                       class="h-4 w-4 rounded border-slate-300 text-navy-600 focus:ring-navy-500">
                <label for="is_active" class="ml-2 text-sm text-slate-700">Active</label>
            </div>
        </div>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
            <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">Save changes</button>
            <a href="{{ route('admin.finalists.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>

    <div class="mt-6 max-w-3xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-navy-900">Project Presentation</h2>
        <p class="mt-1 text-sm text-slate-500">
            Accepted formats: <span class="font-medium">.ppt</span>, <span class="font-medium">.pptx</span>, <span class="font-medium">.pdf</span> &middot; maximum 50 MB.
            The file is stored privately and is only reachable by administrators and judges.
        </p>

        @if ($finalist->hasPresentation())
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-semibold text-emerald-900">Presentation uploaded</p>
                <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-emerald-700">File</dt>
                        <dd class="break-all text-emerald-900">{{ $finalist->presentation_original_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-emerald-700">Type</dt>
                        <dd class="text-emerald-900">{{ strtoupper(pathinfo($finalist->presentation_original_name, PATHINFO_EXTENSION)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-emerald-700">Size</dt>
                        <dd class="text-emerald-900">{{ $finalist->presentationSizeForHumans() ?? 'n/a' }}</dd>
                    </div>
                </dl>
                @if ($finalist->presentation_uploaded_at)
                    <p class="mt-2 text-xs text-emerald-700">Uploaded {{ \App\Support\DateTimeDisplay::formatShort($finalist->presentation_uploaded_at) }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                    @include('partials.presentation-actions', [
                        'finalist' => $finalist,
                        'primaryClass' => 'rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800',
                    ])
                </div>
            </div>
        @else
            <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Presentation has not been uploaded yet.
            </p>
        @endif

        <form method="POST" action="{{ route('admin.presentations.store', $finalist) }}" enctype="multipart/form-data" class="mt-4 rounded-md bg-slate-50 p-4">
            @csrf
            <label for="presentation" class="block text-sm font-medium text-slate-700">
                {{ $finalist->hasPresentation() ? 'Replace presentation' : 'Upload presentation' }}
            </label>
            <input id="presentation" type="file" name="presentation" accept=".ppt,.pptx,.pdf" required
                   class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-navy-800 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-700">
            <button type="submit" class="mt-3 rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">
                {{ $finalist->hasPresentation() ? 'Replace file' : 'Upload file' }}
            </button>
        </form>

        @if ($finalist->hasPresentation())
            <form method="POST" action="{{ route('admin.presentations.destroy', $finalist) }}" class="mt-3"
                  onsubmit="return confirm('Remove the presentation of {{ $finalist->code }}? The stored file will be deleted.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Remove presentation</button>
            </form>
        @endif
    </div>
@endsection
