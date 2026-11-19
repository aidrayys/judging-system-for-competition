@extends('layouts.app')

@section('title', 'Finalist Presentations - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-navy-900">Finalist Presentations</h1>
        <p class="text-sm text-slate-500">
            Upload, replace, open or remove the private presentation file of each finalist.
            Accepted formats: <span class="font-medium">.ppt</span>, <span class="font-medium">.pptx</span>, <span class="font-medium">.pdf</span> &middot; maximum {{ number_format($maxKb / 1024) }} MB per file.
            Files are stored privately and are only reachable through an authenticated download route.
        </p>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search finalist name, project title or file name"
               class="min-w-60 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
        <select name="category" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
            <option value="">Any upload status</option>
            <option value="uploaded" @selected(request('status') === 'uploaded')>Uploaded</option>
            <option value="missing" @selected(request('status') === 'missing')>Not uploaded</option>
        </select>
        <button type="submit" class="rounded-md bg-navy-800 px-4 py-2 text-sm font-medium text-white hover:bg-navy-700">Filter</button>
        <a href="{{ route('admin.presentations.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
    </form>

    <div class="space-y-4">
        @forelse ($finalists as $finalist)
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-navy-700">{{ $finalist->code }}</span>
                            <span class="rounded bg-navy-50 px-2 py-0.5 text-xs font-medium text-navy-800">{{ $finalist->category->name }}</span>
                            @include('partials.status-badge', ['state' => $finalist->hasPresentation() ? 'uploaded' : 'missing'])
                            @if (! $finalist->is_active)
                                @include('partials.status-badge', ['state' => 'inactive'])
                            @endif
                        </div>
                        <p class="mt-1 font-semibold text-navy-900">{{ $finalist->name }}</p>
                        <p class="text-sm text-slate-600">{{ $finalist->department }}</p>
                        <p class="mt-1 max-w-3xl text-sm text-slate-700">{{ $finalist->project_title }}</p>

                        <div class="mt-2 text-xs text-slate-500">
                            @if ($finalist->hasPresentation())
                                <p>
                                    <span class="font-medium text-slate-700">File:</span> {{ $finalist->presentation_original_name }}
                                    &middot; <span class="font-medium text-slate-700">Type:</span> {{ strtoupper(pathinfo($finalist->presentation_original_name, PATHINFO_EXTENSION)) }}
                                    &middot; <span class="font-medium text-slate-700">Size:</span> {{ $finalist->presentationSizeForHumans() ?? 'n/a' }}
                                    @if ($finalist->presentation_uploaded_at)
                                        &middot; <span class="font-medium text-slate-700">Uploaded:</span> {{ \App\Support\DateTimeDisplay::formatShort($finalist->presentation_uploaded_at) }}
                                    @endif
                                </p>
                            @else
                                <p class="text-amber-700">Presentation has not been uploaded yet.</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:w-72">
                        <form method="POST" action="{{ route('admin.presentations.store', $finalist) }}" enctype="multipart/form-data"
                              class="rounded-md border border-slate-200 bg-slate-50 p-3">
                            @csrf
                            <label for="presentation-{{ $finalist->id }}" class="block text-xs font-medium text-slate-700">
                                {{ $finalist->hasPresentation() ? 'Replace presentation' : 'Upload presentation' }}
                            </label>
                            <input id="presentation-{{ $finalist->id }}" type="file" name="presentation" accept=".ppt,.pptx,.pdf" required
                                   class="mt-1 block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-navy-800 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-700">
                            <button type="submit" class="mt-2 w-full rounded-md bg-navy-900 px-3 py-2 text-xs font-semibold text-white hover:bg-navy-800">
                                {{ $finalist->hasPresentation() ? 'Replace file' : 'Upload file' }}
                            </button>
                        </form>

                        @if ($finalist->hasPresentation())
                            <div class="flex gap-2">
                                <a href="{{ route('presentations.open', $finalist) }}" target="_blank" rel="noopener"
                                   class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-center text-xs font-medium text-slate-700 hover:bg-slate-50">View file</a>
                                <a href="{{ route('presentations.download', $finalist) }}"
                                   class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-center text-xs font-medium text-slate-700 hover:bg-slate-50">Download</a>
                                <form method="POST" action="{{ route('admin.presentations.destroy', $finalist) }}"
                                      onsubmit="return confirm('Remove the presentation of {{ $finalist->code }}? The file will be deleted.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="w-full rounded-md border border-rose-300 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500">No finalists match the current filter.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $finalists->links() }}</div>
@endsection
