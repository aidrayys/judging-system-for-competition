/**
 * Wiring for the judge evaluation page.
 *
 * Responsibilities:
 *  - keep the score state in sync with the clicked buttons
 *  - recalculate the summary immediately (no page refresh)
 *  - autosave the draft to the server and report Saving / Saved / Save failed
 *  - never discard the judge's selections when a save fails
 *  - only enable "Submit Evaluation" when all criteria are scored AND the
 *    current state has been saved successfully
 */
import {
    buildPayload,
    collectScores,
    computeSummary,
    isValidScore,
} from './evaluation-scoring.js';

const DEBOUNCE_MS = 1200;

// Expose the initialiser for the Blade view (Vite bundles this file as a
// module entry, so it does not export anything globally by itself).
if (typeof window !== 'undefined') {
    window.JudgingEvaluation = { initEvaluation };
}

export function initEvaluation(config) {
    const {
        autosaveUrl,
        correctionUrl = null,
        csrfToken,
        criteria,
        readOnly = false,
        correctionMode = false,
        displayTimezone = 'UTC',
        submitUrl = null,
        lastSavedAt = null,
        submitted = false,
    } = config;

    const radios = Array.from(document.querySelectorAll('[data-criterion]'));
    if (! radios.length) return null;

    const statusEl = document.getElementById('autosave-status');
    const totalEl = document.getElementById('total-preview');
    const scoredCountEl = document.getElementById('scored-count');
    const totalLabelEl = document.getElementById('total-label');
    const commentEl = document.getElementById('comment');
    const submitButton = document.getElementById('submit-button');
    const submitHint = document.getElementById('submit-hint');
    const retryButton = document.getElementById('retry-save');

    /*
     * Collect the display nodes ONCE with static selectors and index them by
     * the criterion id they belong to.
     *
     * Building a selector per update (for example '[data-selected-score]["1"]')
     * is invalid CSS - an attribute name may not start with a digit - and
     * throws a SyntaxError in the browser, which silently aborts the whole
     * summary update. A node map avoids dynamic selectors completely.
     */
    function collectNodes(attribute) {
        const map = {};
        document.querySelectorAll('[' + attribute + ']').forEach((el) => {
            map[el.getAttribute(attribute)] = el;
        });
        return map;
    }

    const nodes = {
        selectedScore: collectNodes('data-selected-score'),
        selectedLabel: collectNodes('data-selected-label'),
        contributionCard: collectNodes('data-contribution-card'),
        summaryContribution: collectNodes('data-summary-contribution'),
        summaryScore: collectNodes('data-summary-score'),
    };

    // The authoritative state of the form.
    let state = { scores: {}, comment: commentEl ? commentEl.value : '' };
    let savedSignature = null;   // signature of the state that is persisted
    let dirty = false;
    let timer = null;
    let inFlight = false;
    let pendingSave = false;     // a change arrived while a save was in flight

    // Unscored criteria (null) are ignored: the server only returns the
    // scores it stores, the buttons report every criterion.
    const signatureOf = (scores, comment) => JSON.stringify({
        scores: Object.keys(scores).filter((k) => scores[k] !== null && scores[k] !== undefined)
            .sort().map((k) => [k, Number(scores[k])]),
        comment: comment || '',
    });

    function setStatus(text, tone) {
        if (! statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'mt-1 text-sm font-medium ' + (tone || 'text-slate-700');
    }

    function setText(map, id, value) {
        const el = map[id];
        if (el) {
            el.textContent = value;
        }
    }

    /**
     * Format an ISO timestamp in the display timezone.
     *
     * The server sends the timestamp with its offset, the browser renders it
     * with the English locale and the explicit time zone, so the value is
     * never shifted by the timezone of the client.
     */
    function formatTimestamp(value) {
        if (! value) return '';

        try {
            const formatted = new Intl.DateTimeFormat('en-GB', {
                timeZone: displayTimezone,
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
                timeZoneName: 'short',
            }).format(new Date(value));

            return formatted;
        } catch (error) {
            return String(value);
        }
    }

    /**
     * Recalculate and paint the summary from the given state.
     * @param {boolean} fromServer when true the values come from the server
     */
    function render(fromServer = false) {
        const summary = computeSummary(criteria, state.scores);

        summary.rows.forEach((row) => {
            setText(nodes.selectedScore, row.id, row.score === null ? '-' : row.score);
            setText(nodes.selectedLabel, row.id, row.label);
            setText(nodes.contributionCard, row.id, row.contribution.toFixed(2));
            setText(nodes.summaryContribution, row.id, row.contribution.toFixed(2));
            setText(nodes.summaryScore, row.id, row.score === null ? '-' : row.score);
        });

        if (totalEl) {
            totalEl.textContent = summary.total.toFixed(2);
        }

        if (totalLabelEl) {
            totalLabelEl.textContent = summary.provisional
                ? 'Provisional - ' + summary.scoredCount + ' of ' + summary.criteriaCount + ' criteria scored'
                : 'Weighted score preview (complete)';
        }

        if (scoredCountEl) {
            scoredCountEl.textContent = summary.scoredCount;
        }

        updateSubmitState(summary);

        return summary;
    }

    function updateSubmitState(summary) {
        if (! submitButton) return;

        const currentSignature = signatureOf(state.scores, state.comment);
        const isSaved = savedSignature === currentSignature;
        const canSubmit = summary.complete && isSaved && ! dirty && ! inFlight;

        submitButton.disabled = ! canSubmit;

        if (submitHint) {
            if (! summary.complete) {
                submitHint.textContent = 'Score all ' + summary.criteriaCount + ' criteria to enable submission.';
                submitHint.className = 'mt-2 text-center text-xs text-amber-700';
            } else if (dirty || ! isSaved) {
                submitHint.textContent = 'Waiting for the draft to be saved...';
                submitHint.className = 'mt-2 text-center text-xs text-amber-700';
            } else {
                submitHint.textContent = 'All criteria scored and saved. Ready to submit.';
                submitHint.className = 'mt-2 text-center text-xs text-slate-500';
            }
        }
    }

    function readScoresFromButtons() {
        return collectScores(radios);
    }

    function markDirty() {
        state.scores = readScoresFromButtons();
        if (commentEl) state.comment = commentEl.value;
        dirty = true;

        // A rendering problem must never block the save.
        try {
            render(false);
        } catch (error) {
            reportError('Could not update the summary', error);
        }

        if (correctionMode) {
            // The submitted evaluation is corrected as a whole: the scores are
            // sent with the correction form, never with the draft autosave.
            setStatus('Unsaved corrections...', 'text-amber-700');
            return;
        }

        setStatus('Unsaved changes...', 'text-slate-600');
        scheduleSave();
    }

    function reportError(context, error) {
        if (window.console && window.console.error) {
            window.console.error('[Judging System for Competition evaluation] ' + context + ':', error);
        }
    }

    function scheduleSave() {
        if (readOnly) return;
        clearTimeout(timer);
        timer = setTimeout(save, DEBOUNCE_MS);
    }

    /**
     * The autosave endpoint.
     *
     * The URL is built from the current location so that the request always
     * goes to the very same host and port the judge is currently using
     * (localhost, a LAN address or a tunneled host). An absolute URL built
     * from APP_URL would fail with "could not reach the server" whenever the
     * browser uses a different host.
     */
    function resolveAutosaveUrl() {
        if (! autosaveUrl) {
            return null;
        }

        if (/^https?:\/\//i.test(autosaveUrl)) {
            try {
                const target = new URL(autosaveUrl);
                const current = window.location;
                if (target.host !== current.host || target.protocol !== current.protocol) {
                    return current.pathname.replace(/\/$/, '') + '/autosave';
                }
            } catch (e) {
                // fall through and use the path below
            }
        }

        return autosaveUrl;
    }

    const endpoint = resolveAutosaveUrl();

    async function save() {
        if (readOnly) return;
        if (inFlight) {
            // Never drop a change made during a running request: save again
            // as soon as the current request finishes.
            pendingSave = true;
            return;
        }

        // Re-read the buttons so nothing typed while the request was pending
        // is lost.
        state.scores = readScoresFromButtons();
        if (commentEl) state.comment = commentEl.value;

        const invalid = Object.keys(state.scores).filter(
            (id) => state.scores[id] !== null && ! isValidScore(state.scores[id])
        );

        if (invalid.length) {
            setStatus('Save failed: a score is outside the 1-5 range.', 'text-rose-600');
            return;
        }

        const payload = buildPayload(state.scores, state.comment);
        const sentComment = state.comment;
        inFlight = true;
        setStatus('Saving...', 'text-amber-600');
        updateSubmitState(computeSummary(criteria, state.scores));

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            let data = {};
            try {
                data = await response.json();
            } catch (parseError) {
                data = {};
            }

            if (! response.ok) {
                // The server answered with a real error (validation, closed
                // judging period, already submitted, ...). Keep the state.
                const detail = data.errors
                    ? ' (' + Object.values(data.errors).flat().join(' ') + ')'
                    : '';
                const message = data.message || ('Save failed (HTTP ' + response.status + ').');
                setStatus('Save failed: ' + message + detail, 'text-rose-600');
                dirty = true;
                updateSubmitState(computeSummary(criteria, state.scores));
                return;
            }

            // The server is the source of truth for the saved state.
            if (data.scores) {
                const serverScores = {};
                Object.keys(data.scores).forEach((id) => {
                    const value = data.scores[id];
                    serverScores[id] = isValidScore(value) ? Number(value) : null;
                });
                state.scores = serverScores;
            }

            // Only what was actually SENT and confirmed counts as saved.
            savedSignature = signatureOf(state.scores, sentComment);

            // Edits made while the request was running are still unsaved.
            const currentScores = readScoresFromButtons();
            const currentComment = commentEl ? commentEl.value : sentComment;
            if (signatureOf(currentScores, currentComment) !== savedSignature) {
                state.scores = currentScores;
                state.comment = currentComment;
                dirty = true;
                pendingSave = true;
                render(true);
                setStatus('Unsaved changes...', 'text-slate-600');
            } else {
                state.comment = sentComment;
                dirty = false;
                render(true);
                setStatus('Saved at ' + formatTimestamp(data.saved_at), 'text-emerald-700');
            }
        } catch (error) {
            // A genuine network / runtime problem. The selections stay on
            // screen and the judge can retry.
            reportError('Autosave request to ' + endpoint + ' failed', error);
            setStatus(
                'Save failed: could not reach the server (' + (endpoint || 'unknown endpoint') + '). '
                + 'Your changes are still on screen - press Save again.',
                'text-rose-600'
            );
            dirty = true;
            updateSubmitState(computeSummary(criteria, state.scores));
        } finally {
            inFlight = false;
            updateSubmitState(computeSummary(criteria, state.scores));
            if (pendingSave) {
                pendingSave = false;
                if (dirty) scheduleSave();
            }
        }
    }

    // ---------------------------------------------------------------- events
    radios.forEach((radio) => {
        radio.addEventListener('change', markDirty);
    });

    if (commentEl) {
        commentEl.addEventListener('input', markDirty);
    }

    /* ---------------------------------------------------------------------
     * Submission confirmation.
     *
     * The browser native confirm() dialog is replaced by a custom modal
     * that follows the Judging System design. The form itself is only sent after the
     * judge confirms; the server stays the source of truth.
     * ------------------------------------------------------------------ */
    const submitForm = document.getElementById('submit-form');
    const submitDialog = document.getElementById('submit-confirm-dialog');

    let submitConfirmed = false;
    let submitting = false;

    function setSubmitting(active) {
        if (submitDialog) {
            const confirm = document.getElementById('submit-dialog-confirm');
            const cancel = document.getElementById('submit-dialog-cancel');

            if (confirm) {
                confirm.disabled = active;
                confirm.textContent = active ? 'Submitting...' : 'Yes, Submit';
                confirm.classList.toggle('opacity-60', active);
                confirm.classList.toggle('cursor-not-allowed', active);
            }
            if (cancel) {
                cancel.disabled = active;
            }
        }

        if (submitButton) {
            submitButton.disabled = true;

            const label = submitButton.querySelector('[data-submit-label]');
            if (label) {
                label.textContent = 'Submitting...';
            }
        }
    }

    if (submitForm && submitDialog) {
        submitForm.addEventListener('submit', (event) => {
            // The first submit only opens the confirmation modal.
            if (submitConfirmed) {
                submitConfirmed = false;

                return;
            }

            event.preventDefault();
            showDialog(submitDialog, 'submit-dialog-confirm');
        });
    }

    bindDialog(submitDialog, 'submit-dialog-cancel', 'submit-dialog-confirm', () => {
        // A second click is ignored, so the evaluation can never be
        // submitted twice.
        if (submitting) return;

        submitting = true;

        if (! submitForm) return;

        // The buttons are disabled and the loading state is shown, so a
        // double click cannot send the form twice.
        setSubmitting(true);
        submitConfirmed = true;

        if (typeof submitForm.submit === 'function') {
            submitForm.submit();
        }
    });

    /* ---------------------------------------------------------------------
     * Correction mode: a judge corrects their own submitted evaluation.
     *
     * The default state is read-only and only offers "Edit My Scores" and
     * "Reset My Scores". Both actions are confirmed with a dialog; the
     * correction mode itself only offers "Save Corrections" and "Cancel".
     * ------------------------------------------------------------------ */
    const editButton = document.getElementById('edit-my-scores');
    const resetButton = document.getElementById('reset-my-scores');
    const readOnlyActions = document.getElementById('submitted-evaluation-actions');
    const correctionForm = document.getElementById('correction-form');
    const cancelCorrection = document.getElementById('cancel-correction');
    const resetForm = document.getElementById('reset-my-scores-form');
    const messageEl = document.getElementById('correction-message');

    const editDialog = document.getElementById('edit-confirm-dialog');
    const saveDialog = document.getElementById('save-confirm-dialog');
    const resetDialog = document.getElementById('reset-confirm-dialog');

    let resetConfirmed = false;
    let discarding = false;

    function showDialog(dialog, confirmId) {
        if (! dialog) return;
        dialog.classList.remove('hidden');
        dialog.classList.add('flex');

        const confirm = confirmId ? document.getElementById(confirmId) : null;
        if (confirm && typeof confirm.focus === 'function') confirm.focus();
    }

    function hideDialog(dialog) {
        if (! dialog) return;
        dialog.classList.add('hidden');
        dialog.classList.remove('flex');
    }

    function bindDialog(dialog, cancelId, confirmId, onConfirm) {
        if (! dialog) return;

        const cancel = document.getElementById(cancelId);
        const confirm = document.getElementById(confirmId);

        if (cancel) cancel.addEventListener('click', () => hideDialog(dialog));
        if (confirm) confirm.addEventListener('click', () => {
            hideDialog(dialog);
            onConfirm();
        });
    }

    function setMessage(text, tone) {
        if (! messageEl) return;

        if (! text) {
            messageEl.classList.add('hidden');
            messageEl.textContent = '';

            return;
        }

        messageEl.classList.remove('hidden');
        messageEl.textContent = text;

        const tones = {
            success: 'border border-emerald-200 bg-emerald-50 text-emerald-800',
            error: 'border border-rose-200 bg-rose-50 text-rose-800',
            info: 'border border-gold-200 bg-white text-navy-900',
        };

        messageEl.className = 'mt-3 rounded-md px-3 py-2 text-xs ' + (tones[tone] || tones.info);
    }

    function enterCorrectionMode() {
        radios.forEach((radio) => { radio.disabled = false; });
        if (commentEl) commentEl.disabled = false;

        if (correctionForm) correctionForm.classList.remove('hidden');
        // The read-only actions are hidden completely: correction and reset
        // can never be active at the same time.
        if (readOnlyActions) readOnlyActions.classList.add('hidden');

        setMessage('', null);
        setStatus('Correction mode: change the scores and save your corrections.', 'text-amber-700');
    }

    function leaveCorrectionMode() {
        radios.forEach((radio) => { radio.disabled = true; });
        if (commentEl) commentEl.disabled = true;

        if (correctionForm) correctionForm.classList.add('hidden');
        if (readOnlyActions) readOnlyActions.classList.remove('hidden');

        setStatus('Submitted - use "Edit My Scores" to correct your evaluation.', 'text-slate-500');
    }

    /**
     * Save the corrections. The request is sent with fetch so that a failure
     * keeps the unsaved edits on screen.
     */
    async function saveCorrections() {
        const scores = readScoresFromButtons();
        const payload = { scores: {}, comment: commentEl ? commentEl.value : '' };

        Object.keys(scores).forEach((id) => {
            const value = scores[id];
            payload.scores[id] = (value === null || value === undefined) ? null : Number(value);
        });

        setStatus('Saving corrections...', 'text-amber-600');

        try {
            const response = await fetch(correctionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            let data = {};
            try {
                data = await response.json();
            } catch (parseError) {
                data = {};
            }

            if (! response.ok) {
                const detail = data.errors
                    ? ' (' + Object.values(data.errors).flat().join(' ') + ')'
                    : '';
                setMessage(
                    'Save failed: ' + (data.message || 'the corrections could not be saved.') + detail,
                    'error'
                );
                setStatus('Unsaved corrections...', 'text-amber-700');

                return;
            }

            // The server is the source of truth for the saved state.
            if (data.scores) {
                const serverScores = {};
                Object.keys(data.scores).forEach((id) => {
                    const value = data.scores[id];
                    serverScores[id] = isValidScore(value) ? Number(value) : null;
                });
                state.scores = serverScores;
            }

            savedSignature = signatureOf(state.scores, state.comment);
            dirty = false;

            leaveCorrectionMode();
            render(true);
            setMessage(
                'Your corrections have been saved. The evaluation stays submitted with the updated scores.',
                'success'
            );
            setStatus('Submitted at ' + formatTimestamp(data.saved_at), 'text-emerald-700');
        } catch (error) {
            reportError('Correction request to ' + correctionUrl + ' failed', error);
            setMessage(
                'Save failed: the server could not be reached. Your changes are still on screen - please try again.',
                'error'
            );
            setStatus('Unsaved corrections...', 'text-amber-700');
        }
    }

    if (editButton && correctionMode) {
        editButton.addEventListener('click', () => {
            showDialog(editDialog, 'edit-dialog-confirm');
        });
    }

    bindDialog(editDialog, 'edit-dialog-cancel', 'edit-dialog-confirm', () => {
        enterCorrectionMode();
    });

    bindDialog(saveDialog, 'save-dialog-cancel', 'save-dialog-confirm', () => {
        saveCorrections();
    });

    bindDialog(resetDialog, 'reset-dialog-cancel', 'reset-dialog-confirm', () => {
        resetConfirmed = true;

        if (resetForm && typeof resetForm.submit === 'function') {
            resetForm.submit();
        }
    });

    if (correctionForm && correctionMode) {
        correctionForm.addEventListener('submit', (event) => {
            // The correction is only sent after the confirmation dialog.
            event.preventDefault();
            showDialog(saveDialog, 'save-dialog-confirm');
        });
    }

    if (cancelCorrection) {
        cancelCorrection.addEventListener('click', () => {
            // Nothing is saved: the page is reloaded, so the last persisted
            // scores and the feedback are restored.
            discarding = true;

            if (typeof window.location !== 'undefined' && typeof window.location.reload === 'function') {
                window.location.reload();
            }
        });
    }

    if (resetButton && correctionMode) {
        resetButton.addEventListener('click', () => {
            showDialog(resetDialog, 'reset-dialog-confirm');
        });
    }

    if (resetForm) {
        resetForm.addEventListener('submit', (event) => {
            if (resetConfirmed) {
                resetConfirmed = false;

                return;
            }

            event.preventDefault();
            showDialog(resetDialog, 'reset-dialog-confirm');
        });
    }

    if (retryButton) {
        retryButton.addEventListener('click', () => {
            clearTimeout(timer);
            save();
        });
    }

    // Do not lose unsaved work when the page is closed.
    window.addEventListener('beforeunload', (event) => {
        if (dirty && ! discarding) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    // Initial state from the server rendered page.
    state.scores = readScoresFromButtons();
    if (commentEl) state.comment = commentEl.value;
    savedSignature = signatureOf(state.scores, state.comment);
    dirty = false;
    render(true);

    // The page is always rendered from the DB (no-store), so the loaded values
    // ARE the last successfully saved state.
    if (correctionMode) {
        setStatus('Submitted / Locked - use "Edit My Scores" to correct your evaluation.', 'text-emerald-700');
    } else if (submitted) {
        setStatus('Submitted / Locked - no changes can be made.', 'text-emerald-700');
    } else if (readOnly) {
        setStatus('Read-only - no changes can be made.', 'text-slate-500');
    } else if (lastSavedAt) {
        setStatus('Saved at ' + formatTimestamp(lastSavedAt) + ' (loaded from server)', 'text-emerald-700');
    } else {
        setStatus('No changes yet', 'text-slate-700');
    }

    return { save, render, get state() { return state; } };
}
