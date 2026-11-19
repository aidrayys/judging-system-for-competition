@extends('layouts.app')

@section('title', 'Evaluate '.$finalist->code.' - Judging System for Competition')

@section('content')
    @php
        $readOnly = ($evaluation && ! $evaluation->isDraft()) || ! $judgingOpen;
        // A judge may correct their own submitted evaluation while the
        // judging period is open. The correction is confirmed and audited.
        $canCorrect = $judgingOpen && $evaluation && $evaluation->isSubmitted();
        $correctionUrl = request()->getBaseUrl().route('judge.correct', [$category, $finalist], false);
        $scores = $scores ?? [];
        $contributions = $contributions ?? [];
        $scoredCount = count(array_intersect(array_keys($scores), $criteria->pluck('id')->all()));
        $isComplete = $scoredCount === $criteria->count();
    @endphp

    <div class="mb-4">
        <a href="{{ route('judge.dashboard') }}" class="text-sm text-navy-700 hover:underline">&larr; Back to my judging</a>
    </div>

    @unless ($judgingOpen)
        <div class="mb-5 rounded-lg border-2 border-amber-300 bg-amber-50 p-4" role="alert">
            <p class="font-semibold text-amber-900">The judging period is currently closed.</p>
            <p class="mt-1 text-sm text-amber-800">
                You can still open a finalist and review your own work, but saving and submitting are disabled
                until the administrator reopens the judging period. Please contact the administrator if you
                believe this is an error.
            </p>
        </div>
    @endunless

    @if ($evaluation && $evaluation->isSubmitted())
        <div class="mb-5 rounded-lg border-2 border-emerald-200 bg-emerald-50 p-4" role="status">
            <p class="font-semibold text-emerald-900">
                @if ($canCorrect)
                    This evaluation has been submitted. You may correct your own scores while the judging period is open.
                @else
                    This evaluation has been submitted and is read-only.
                @endif
            </p>
            <p class="mt-1 text-sm text-emerald-800">
                Submitted on {{ \App\Support\DateTimeDisplay::format($evaluation->submitted_at) }}.
                @if ($canCorrect)
                    Use &quot;Edit My Scores&quot; in the evaluation summary to change a score or your feedback, or
                    &quot;Reset My Scores&quot; to start over. Every change is recorded in the audit log.
                @else
                    Contact the administrator if a correction is required - only an administrator can reopen a
                    submitted evaluation.
                @endif
            </p>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gold-600">{{ $category->name }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-navy-900">{{ $finalist->name }}</h1>
                <p class="text-sm text-slate-500">{{ $finalist->department }} &middot; <span class="font-mono text-xs">{{ $finalist->code }}</span></p>
                <p class="mt-3 text-base text-slate-800">{{ $finalist->project_title }}</p>
                @if (filled($finalist->description))
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $finalist->description }}</p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-navy-900">Project Presentation</h2>
                @if ($finalist->hasPresentation())
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">File</dt>
                            <dd class="break-all text-slate-800">{{ $finalist->presentation_original_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Type</dt>
                            <dd class="text-slate-800">{{ strtoupper(pathinfo($finalist->presentation_original_name, PATHINFO_EXTENSION)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Size</dt>
                            <dd class="text-slate-800">{{ $finalist->presentationSizeForHumans() ?? 'n/a' }}</dd>
                        </div>
                    </dl>
                    @include('partials.presentation-actions', ['finalist' => $finalist])
                @else
                    <p class="mt-3 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">Presentation has not been uploaded yet.</p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-navy-900">Scoring criteria</h2>
                    <span class="text-xs text-slate-500">Active weight total: {{ number_format($weightTotal, 2) }}%</span>
                </div>

                <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Scoring guide</p>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
                        @foreach ($guide as $band)
                            <span><span class="font-semibold text-navy-900">{{ $band['range'] }}</span> &middot; {{ $band['label'] }}</span>
                        @endforeach
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">This is a general guide for interpreting the 1&ndash;5 scale. It is not an additional criterion and carries no weight.</p>
                </div>

                <div class="mt-4 space-y-4">
                    @foreach ($criteria as $criterion)
                        @php
                            $value = $scores[$criterion->id] ?? null;
                            $contribution = $contributions[$criterion->id] ?? 0;
                            $label = \App\Support\Rubric::labelForScore($value);
                        @endphp
                        <fieldset class="rounded-md border border-slate-200 p-4" data-criterion-field="{{ $criterion->id }}">
                            <legend class="sr-only">{{ $criterion->name }}</legend>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-navy-900" id="criterion-label-{{ $criterion->id }}">
                                    {{ $criterion->display_order }}. {{ $criterion->name }}
                                </span>
                                <span class="rounded bg-navy-50 px-2 py-0.5 text-xs font-semibold text-navy-800">Weight {{ number_format($criterion->weight, 2) }}%</span>
                            </div>

                            @if ($criterion->description)
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $criterion->description }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <div class="flex flex-wrap gap-1.5" role="group" aria-labelledby="criterion-label-{{ $criterion->id }}">
                                    @for ($i = \App\Services\ScoringService::MIN_SCORE; $i <= \App\Services\ScoringService::MAX_SCORE; $i++)
                                        <div class="relative">
                                            <input id="score-{{ $criterion->id }}-{{ $i }}"
                                                   type="radio"
                                                   name="scores[{{ $criterion->id }}]"
                                                   value="{{ $i }}"
                                                   @checked($value === $i)
                                                   @disabled($readOnly)
                                                   data-criterion="{{ $criterion->id }}"
                                                   data-weight="{{ $criterion->weight }}"
                                                   aria-label="Score {{ $i }} for {{ $criterion->name }}"
                                                   class="peer sr-only">
                                            <label for="score-{{ $criterion->id }}-{{ $i }}"
                                                   class="flex h-10 w-10 cursor-pointer select-none items-center justify-center rounded-md border border-slate-300 bg-white text-sm font-semibold text-slate-700 transition hover:border-navy-500 hover:text-navy-800 peer-checked:border-navy-900 peer-checked:bg-navy-900 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-navy-500 peer-focus-visible:ring-offset-1 peer-disabled:cursor-not-allowed peer-disabled:opacity-60 peer-disabled:hover:border-slate-300 peer-disabled:hover:text-slate-700">
                                                {{ $i }}
                                            </label>
                                        </div>
                                    @endfor
                                </div>

                                <span class="text-xs text-slate-500">Select 1&ndash;5</span>

                                <span class="ml-auto flex items-center gap-3 text-xs font-medium text-slate-600">
                                    <span>Selected:
                                        <span class="font-semibold text-navy-900" data-selected-score="{{ $criterion->id }}">{{ $value ?? '-' }}</span>
                                        <span class="font-normal text-slate-500" data-selected-label="{{ $criterion->id }}">{{ $label ?? '' }}</span>
                                    </span>
                                    <span>Contribution:
                                        <span class="font-semibold text-navy-900" data-contribution-card="{{ $criterion->id }}">{{ number_format($contribution, 2) }}</span>
                                    </span>
                                </span>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <label for="comment" class="text-lg font-semibold text-navy-900">Feedback</label>
                <p class="mt-1 text-xs text-slate-500">Optional. Visible to the administrator only.</p>
                <textarea id="comment" name="comment" rows="5" @disabled($readOnly) placeholder="Your observations, strengths and suggestions for this proposal..."
                          class="mt-3 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-navy-500 focus:outline-none focus:ring-1 focus:ring-navy-500 disabled:bg-slate-100">{{ $comment ?? '' }}</textarea>
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-6">
                <h2 class="text-base font-semibold text-navy-900">Evaluation summary</h2>

                <div class="mt-4 rounded-lg bg-navy-900 p-4 text-white">
                    <p class="text-xs uppercase tracking-wide text-navy-200" id="total-label">Weighted score preview</p>
                    <p class="mt-1 text-4xl font-bold" id="total-preview">{{ number_format(array_sum($contributions), 2) }}</p>
                    <p class="mt-1 text-xs text-navy-200">Maximum 100 &middot; recalculated by the server on every save</p>
                </div>

                <p class="mt-3 text-xs text-slate-600">
                    Criteria scored: <span id="scored-count" class="font-semibold text-navy-900">{{ $scoredCount }}</span> of {{ $criteria->count() }}
                </p>

                <div class="mt-3 space-y-1 text-sm">
                    @foreach ($criteria as $criterion)
                        <div class="flex items-center justify-between gap-2 text-slate-600">
                            <span class="truncate">{{ $criterion->name }}</span>
                            <span class="flex items-center gap-2 font-medium text-navy-900">
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs" data-summary-score="{{ $criterion->id }}">{{ $scores[$criterion->id] ?? '-' }}</span>
                                <span data-summary-contribution="{{ $criterion->id }}">{{ number_format($contributions[$criterion->id] ?? 0, 2) }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 border-t border-slate-200 pt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Autosave status</p>
                    <p id="autosave-status" class="mt-1 text-sm font-medium text-slate-700" role="status" aria-live="polite">
                        @if (! $judgingOpen)
                            Judging period closed
                        @elseif ($evaluation && $evaluation->isSubmitted())
                            Submitted - no further changes
                        @else
                            Idle
                        @endif
                    </p>
                    @if (! $readOnly)
                        <button type="button" id="retry-save" class="mt-2 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Save again
                        </button>
                    @endif
                </div>

                @if ($canCorrect)
                    {{-- ================= Correct / reset a submitted evaluation ================= --}}
                    <div class="mt-4 rounded-lg border border-gold-300 bg-gold-100/40 p-4" id="submitted-evaluation">
                        <h2 class="text-sm font-semibold text-navy-900">Submitted evaluation</h2>
                        <p class="mt-1 text-xs text-slate-600">
                            Submitted on {{ \App\Support\DateTimeDisplay::format($evaluation->submitted_at) }}.
                            You may correct your own scores while the judging period is open. Every correction is
                            recorded in the audit log.
                        </p>

                        {{-- Feedback of the last action (correction / reset) --}}
                        <div id="correction-message" class="mt-3 hidden rounded-md px-3 py-2 text-xs" role="status" aria-live="polite"></div>

                        {{-- Default state: read-only. Only the two entry points are shown. --}}
                        <div id="submitted-evaluation-actions" class="mt-3 space-y-2">
                            <button type="button" id="edit-my-scores"
                                    class="w-full rounded-md bg-navy-900 px-3 py-2 text-xs font-semibold text-white hover:bg-navy-800">
                                Edit My Scores
                            </button>
                            <button type="button" id="reset-my-scores"
                                    class="w-full rounded-md border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                Reset My Scores
                            </button>
                        </div>

                        {{-- Correction mode: only Save Corrections and Cancel. No reason field. --}}
                        <form id="correction-form" method="POST" action="{{ route('judge.correct', [$category, $finalist]) }}" class="mt-3 hidden">
                            @csrf
                            @foreach ($criteria as $criterion)
                                <input type="hidden" name="scores[{{ $criterion->id }}]" data-correction-score="{{ $criterion->id }}" value="">
                            @endforeach
                            <input type="hidden" name="comment" value="">
                            <div class="flex gap-2">
                                <button type="submit" id="save-corrections"
                                        class="flex-1 rounded-md bg-gold-500 px-3 py-2 text-xs font-bold text-navy-900 hover:bg-gold-400">
                                    Save Corrections
                                </button>
                                <button type="button" id="cancel-correction" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Cancel
                                </button>
                            </div>
                        </form>

                        {{-- The reset itself needs no reason: the confirmation dialog is enough. --}}
                        <form id="reset-my-scores-form" method="POST" action="{{ route('judge.reset', [$category, $finalist]) }}" class="hidden">
                            @csrf
                        </form>
                    </div>
                @endif

                @if (! $readOnly)
                    <form method="POST" action="{{ route('judge.submit', [$category, $finalist]) }}" class="mt-4" id="submit-form">
                        @csrf
                        <button type="submit" id="submit-button" @disabled(! $isComplete)
                                class="w-full rounded-md bg-gold-500 px-4 py-2.5 text-sm font-bold text-navy-900 hover:bg-gold-400 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500">
                            <span data-submit-label>Submit Evaluation</span>
                        </button>
                        <p id="submit-hint" class="mt-2 text-center text-xs {{ $isComplete ? 'text-slate-500' : 'text-amber-700' }}">
                            @if ($isComplete)
                                All criteria scored. Ready to submit.
                            @else
                                Score all {{ $criteria->count() }} criteria to enable submission.
                            @endif
                        </p>
                    </form>
                @else
                    <a href="{{ route('judge.submitted', [$category, $finalist]) }}"
                       class="mt-4 block w-full rounded-md border border-slate-300 px-4 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                        View submission status
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (! $readOnly)
        {{-- ================= Submit confirmation ================= --}}
        <div id="submit-confirm-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-navy-950/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="submit-confirm-title" aria-describedby="submit-confirm-text">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 id="submit-confirm-title" class="text-base font-semibold text-navy-900">Submit Evaluation?</h3>
                <p id="submit-confirm-text" class="mt-2 text-sm text-slate-600">
                    Are you sure you want to submit this evaluation? Please make sure all six criteria and your feedback
                    are correct before continuing.
                </p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" id="submit-dialog-cancel"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" id="submit-dialog-confirm"
                            class="rounded-md bg-gold-500 px-4 py-2 text-sm font-bold text-navy-900 hover:bg-gold-400">
                        Yes, Submit
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($canCorrect)
        {{-- ================= Confirmation dialogs ================= --}}
        {{-- Edit My Scores --}}
        <div id="edit-confirm-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-navy-950/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="edit-confirm-title" aria-describedby="edit-confirm-text">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 id="edit-confirm-title" class="text-base font-semibold text-navy-900">Correct your evaluation</h3>
                <p id="edit-confirm-text" class="mt-2 text-sm text-slate-600">
                    Are you sure you want to correct your submitted evaluation? You can change your scores and submit
                    the corrections again.
                </p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" id="edit-dialog-cancel"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" id="edit-dialog-confirm"
                            class="rounded-md bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800">
                        Yes, Edit Scores
                    </button>
                </div>
            </div>
        </div>

        {{-- Save Corrections --}}
        <div id="save-confirm-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-navy-950/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="save-confirm-title" aria-describedby="save-confirm-text">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 id="save-confirm-title" class="text-base font-semibold text-navy-900">Save Score Corrections?</h3>
                <p id="save-confirm-text" class="mt-2 text-sm text-slate-600">
                    Are you sure you want to save these changes? The updated scores will replace the previous scores,
                    and the correction will be recorded in the audit log.
                </p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" id="save-dialog-cancel"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" id="save-dialog-confirm"
                            class="rounded-md bg-gold-500 px-4 py-2 text-sm font-bold text-navy-900 hover:bg-gold-400">
                        Yes, Save Corrections
                    </button>
                </div>
            </div>
        </div>

        {{-- Reset My Scores --}}
        <div id="reset-confirm-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-navy-950/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="reset-confirm-title" aria-describedby="reset-confirm-text">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 id="reset-confirm-title" class="text-base font-semibold text-navy-900">Reset Evaluation?</h3>
                <p id="reset-confirm-text" class="mt-2 text-sm text-slate-600">
                    Are you sure you want to reset this evaluation? All six criterion scores and feedback will be
                    cleared. The evaluation status will return to Not Started, and you will need to evaluate all criteria
                    and submit again. The historical audit record will be preserved.
                </p>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" id="reset-dialog-cancel"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" id="reset-dialog-confirm"
                            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                        Yes, Reset
                    </button>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script type="module">
        document.addEventListener('DOMContentLoaded', function () {
            if (! window.JudgingEvaluation) return;

            const criteria = @json($criteriaPayload);
            const autosaveUrl = @json($autosaveUrl);
            const correctionUrl = @json($correctionUrl);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const readOnly = @json($readOnly);
            const canCorrect = @json($canCorrect);

            window.JudgingEvaluation.initEvaluation({
                autosaveUrl: autosaveUrl,
                correctionUrl: correctionUrl,
                csrfToken: csrfToken,
                criteria: criteria,
                readOnly: readOnly,
                correctionMode: canCorrect,
                displayTimezone: @json(config('app.display_timezone', 'UTC')),
                // Status of the data loaded from the DB, shown on (re)load / Back.
                // A blank (e.g. just reset) draft opens like a brand new evaluation.
                lastSavedAt: @json($evaluation && ! $evaluation->isBlankDraft() ? \App\Support\DateTimeDisplay::iso($evaluation->updated_at) : null),
                submitted: @json((bool) ($evaluation && ! $evaluation->isDraft())),
            });
        });
    </script>
@endpush

