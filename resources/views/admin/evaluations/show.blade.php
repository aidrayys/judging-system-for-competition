@extends('layouts.app')

@section('title', 'Evaluation detail - Admin · Judging System for Competition')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.evaluations.index') }}" class="text-sm text-navy-700 hover:underline">&larr; Back to evaluations</a>
        <h1 class="mt-2 text-2xl font-semibold text-navy-900">{{ $evaluation->judge->name }} &middot; {{ $evaluation->finalist->code }}</h1>
        <p class="text-sm text-slate-500">{{ $evaluation->category->name }} &middot; {{ $evaluation->finalist->name }} &middot; {{ $evaluation->finalist->department }}</p>
        <p class="max-w-3xl text-sm text-slate-700">{{ $evaluation->finalist->project_title }}</p>
    </div>

    @if ($release && $release->needsReview())
        <div class="mb-4 rounded-lg border-2 border-amber-300 bg-amber-50 p-4" role="alert">
            <p class="font-semibold text-amber-900">The official result of this category needs to be reviewed.</p>
            <p class="mt-1 text-sm text-amber-800">
                A correction or a reset changed an evaluation after the results were released. The result has to be
                reviewed and republished by an administrator.
            </p>
            <a href="{{ route('admin.results.show', $evaluation->category) }}" class="mt-2 inline-block text-sm font-semibold text-amber-900 underline">
                Open the result page
            </a>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-navy-900">Scores</h2>
                    <p class="text-xs text-slate-500">
                        Weighted contribution = (score / 5) &times; weight &middot; maximum 100
                    </p>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Criterion</th>
                                <th class="px-3 py-2 text-left font-medium">Weight</th>
                                <th class="px-3 py-2 text-left font-medium">Score</th>
                                <th class="px-3 py-2 text-left font-medium">Weighted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($criteria as $criterion)
                                @php
                                    $score = $evaluation->scores->firstWhere('criterion_id', $criterion->id)?->score;
                                    $contribution = $score ? round($score / \App\Services\ScoringService::MAX_SCORE * (float) $criterion->weight, 2) : null;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-slate-800">{{ $criterion->name }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ number_format($criterion->weight, 2) }}%</td>
                                    <td class="px-3 py-2 font-semibold text-navy-900">{{ $score ?? '-' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $contribution !== null ? number_format($contribution, 2) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td class="px-3 py-2 font-semibold text-navy-900" colspan="3">Total weighted score</td>
                                <td class="px-3 py-2 font-bold text-navy-900">{{ $evaluation->total_score !== null ? number_format($evaluation->total_score, 2) : '-' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <h3 class="mt-6 text-base font-semibold text-navy-900">Feedback</h3>
                <p class="mt-2 whitespace-pre-line rounded-md bg-slate-50 p-4 text-sm text-slate-700">{{ $evaluation->comment ?: 'No feedback provided.' }}</p>
            </div>

            {{-- ================= Evaluation management ================= --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" id="evaluation-management">
                <h2 class="text-base font-semibold text-navy-900">Evaluation management</h2>
                <p class="mt-1 text-xs text-slate-500">
                    The original judge keeps this evaluation: a correction never impersonates the judge and never
                    creates a second evaluation record. Every action is written to the audit log with the previous and
                    the new values.
                </p>

                <form method="POST" action="{{ route('admin.evaluations.correct', $evaluation) }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Criterion</th>
                                    <th class="px-3 py-2 text-left font-medium">Weight</th>
                                    <th class="px-3 py-2 text-left font-medium">Previous score</th>
                                    <th class="px-3 py-2 text-left font-medium">Updated score</th>
                                    <th class="px-3 py-2 text-left font-medium">Previous weighted</th>
                                    <th class="px-3 py-2 text-left font-medium">Updated weighted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($criteria as $criterion)
                                    @php
                                        $score = $evaluation->scores->firstWhere('criterion_id', $criterion->id)?->score;
                                        $contribution = $score ? round($score / \App\Services\ScoringService::MAX_SCORE * (float) $criterion->weight, 2) : null;
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-slate-800">{{ $criterion->name }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ number_format($criterion->weight, 2) }}%</td>
                                        <td class="px-3 py-2 font-semibold text-navy-900">{{ $score ?? '-' }}</td>
                                        <td class="px-3 py-2">
                                            <select name="scores[{{ $criterion->id }}]"
                                                    class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">
                                                @for ($i = \App\Services\ScoringService::MIN_SCORE; $i <= \App\Services\ScoringService::MAX_SCORE; $i++)
                                                    <option value="{{ $i }}" @selected($score === $i)>{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">{{ $contribution !== null ? number_format($contribution, 2) : '-' }}</td>
                                        <td class="px-3 py-2 text-slate-500" data-updated-weighted="{{ $criterion->id }}">-</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <label for="correction-comment" class="mt-4 block text-xs font-semibold text-navy-900">Feedback</label>
                    <textarea id="correction-comment" name="comment" rows="3" maxlength="5000"
                              class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500">{{ $evaluation->comment }}</textarea>

                    <label for="admin-correction-reason" class="mt-3 block text-xs font-semibold text-navy-900">
                        Correction reason <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="admin-correction-reason" name="reason" rows="2" required minlength="10" maxlength="1000"
                              class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500"
                              placeholder="Why is this evaluation corrected?"></textarea>

                    @if ($release)
                        <label class="mt-3 flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                            <input type="checkbox" name="confirm_released_results" value="1" class="mt-0.5">
                            <span>
                                The official result of this category was already released. I confirm the results
                                correction workflow: the result is flagged for review and has to be republished.
                            </span>
                        </label>
                    @endif

                    <div class="mt-3 flex gap-2">
                        <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-xs font-semibold text-white hover:bg-navy-800">
                            Save Corrections
                        </button>
                        <a href="{{ route('admin.evaluations.show', $evaluation) }}" class="rounded-md border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            {{-- ================= Correction history ================= --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-navy-900">Correction history</h2>
                <p class="mt-1 text-xs text-slate-500">Every correction and reset of this evaluation, newest first.</p>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Time</th>
                                <th class="px-3 py-2 text-left font-medium">Action</th>
                                <th class="px-3 py-2 text-left font-medium">Actor</th>
                                <th class="px-3 py-2 text-left font-medium">Original judge</th>
                                <th class="px-3 py-2 text-left font-medium">Total</th>
                                <th class="px-3 py-2 text-left font-medium">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($history as $entry)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ \App\Support\DateTimeDisplay::format($entry->created_at) }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded bg-gold-100 px-2 py-0.5 text-xs font-semibold text-gold-700 ring-1 ring-inset ring-gold-300">
                                            {{ $entry->actionTypeLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-navy-900">
                                        {{ $entry->user?->name ?? 'System' }}
                                        <span class="block text-xs text-slate-500">{{ ucfirst((string) $entry->actor_role) }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $entry->originalJudge?->name ?? $evaluation->judge->name }}</td>
                                    <td class="px-3 py-2 text-slate-700">
                                        {{ $entry->previous_total !== null ? number_format($entry->previous_total, 2) : '-' }}
                                        &rarr;
                                        {{ $entry->new_total !== null ? number_format($entry->new_total, 2) : '-' }}
                                        <span class="block text-xs text-slate-500">
                                            {{ $entry->previous_status ?? '-' }} &rarr; {{ $entry->new_status ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs px-3 py-2 text-slate-600">{{ $entry->reason ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-slate-500">
                                        This evaluation has never been corrected or reset.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-navy-900">Status</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">State</dt><dd>@include('partials.status-badge', ['state' => $evaluation->status === 'submitted' ? 'submitted' : ($evaluation->status === 'locked' ? 'locked' : 'draft')])</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Submitted at</dt><dd class="text-right text-slate-700">{{ \App\Support\DateTimeDisplay::formatShort($evaluation->submitted_at) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Reopened</dt><dd class="text-slate-700">{{ $evaluation->reopen_count }}x</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last updated</dt><dd class="text-right text-slate-700">{{ \App\Support\DateTimeDisplay::formatShort($evaluation->updated_at) }}</dd></div>
                </dl>
            </div>

            {{-- ================= Reset ================= --}}
            <div class="rounded-lg border border-rose-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-navy-900">Reset</h2>
                <p class="mt-1 text-sm text-slate-500">
                    A reset clears the scores and the feedback and returns the evaluation to the draft state, so it no
                    longer counts as submitted. The evaluation record and its history are preserved.
                </p>

                <form method="POST" action="{{ route('admin.evaluations.reset-scores', $evaluation) }}" class="mt-3 space-y-2">
                    @csrf
                    <label for="reset-scores-reason" class="block text-xs font-semibold text-navy-900">
                        Reset scores - reason <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="reset-scores-reason" name="reason" rows="2" required minlength="10" maxlength="1000"
                              class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500"
                              placeholder="Why are the scores reset?"></textarea>
                    @if ($release)
                        <label class="flex items-start gap-2 text-xs text-amber-900">
                            <input type="checkbox" name="confirm_released_results" value="1" class="mt-0.5">
                            <span>Confirm the results correction workflow for the released result.</span>
                        </label>
                    @endif
                    <button type="submit" class="w-full rounded-md bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">
                        Reset Scores
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.evaluations.reset-submission', $evaluation) }}" class="mt-4 space-y-2 border-t border-slate-200 pt-4">
                    @csrf
                    <label for="reset-submission-reason" class="block text-xs font-semibold text-navy-900">
                        Reset submission - reason <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="reset-submission-reason" name="reason" rows="2" required minlength="10" maxlength="1000"
                              class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500"
                              placeholder="Why is the submission reset?"></textarea>
                    @if ($release)
                        <label class="flex items-start gap-2 text-xs text-amber-900">
                            <input type="checkbox" name="confirm_released_results" value="1" class="mt-0.5">
                            <span>Confirm the results correction workflow for the released result.</span>
                        </label>
                    @endif
                    <button type="submit" class="w-full rounded-md border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                        Reset Submission
                    </button>
                </form>
            </div>

            @if ($evaluation->status !== 'draft')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-amber-900">Reopen evaluation</h2>
                    <p class="mt-1 text-sm text-amber-900">Reopening lets the judge edit the evaluation again. A reason is required and stored in the audit log.</p>
                    <form method="POST" action="{{ route('admin.evaluations.reopen', $evaluation) }}" class="mt-3 space-y-2">
                        @csrf
                        <label for="reopen-reason" class="block text-xs font-semibold text-amber-900">Reason <span class="text-rose-600">*</span></label>
                        <textarea id="reopen-reason" name="reason" rows="2" required minlength="10"
                                  class="block w-full rounded-md border border-amber-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></textarea>
                        <button type="submit" class="w-full rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Reopen for judge</button>
                    </form>
                </div>
            @endif

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-navy-900">Lock evaluation</h2>
                <p class="mt-1 text-sm text-slate-500">Locking freezes the evaluation for the judge without deleting any data. The reason is recorded in the audit log.</p>
                @if ($evaluation->status !== 'locked')
                    <form method="POST" action="{{ route('admin.evaluations.lock', $evaluation) }}" class="mt-3 space-y-2">
                        @csrf
                        <label for="lock-reason" class="block text-xs font-semibold text-slate-700">Reason <span class="text-rose-600">*</span></label>
                        <textarea id="lock-reason" name="reason" rows="2" required minlength="10"
                                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500"></textarea>
                        <button type="submit" class="w-full rounded-md bg-slate-700 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Lock evaluation</button>
                    </form>
                @else
                    <p class="mt-3 rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">This evaluation is locked.</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        // Show the updated weighted contribution while the administrator picks
        // a new score. The server always recalculates the persisted value.
        document.addEventListener('DOMContentLoaded', function () {
            const rows = Array.from(document.querySelectorAll('select[name^="scores["]'));

            rows.forEach((select) => {
                const row = select.closest('tr');
                const weightCell = row?.children?.[1]?.textContent?.replace('%', '').trim();
                const weight = Number(weightCell);
                const previous = Number(row?.children?.[2]?.textContent?.trim());
                const updatedCell = row?.querySelector('[data-updated-weighted]');

                if (! weight || ! updatedCell) return;

                const update = () => {
                    const score = Number(select.value);
                    const contribution = (score / {{ \App\Services\ScoringService::MAX_SCORE }}) * weight;
                    updatedCell.textContent = score === previous
                        ? 'unchanged'
                        : contribution.toFixed(2);
                };

                select.addEventListener('change', update);
                update();
            });
        });
    </script>
@endpush
