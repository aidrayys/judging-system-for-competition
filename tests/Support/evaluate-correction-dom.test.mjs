/**
 * Node test for the correction / reset workflow of the evaluation page
 * (resources/js/evaluate.js).
 *
 * A minimal DOM shim is used so the real module can run head-less. The test
 * proves the simplified interaction:
 *
 *   - the default state is read-only and only offers "Edit My Scores" and
 *     "Reset My Scores"
 *   - "Edit My Scores" opens a confirmation dialog first
 *   - the correction mode enables the scores and hides the read-only actions,
 *     so correction and reset are never active at the same time
 *   - "Save Corrections" opens a second confirmation dialog and is only sent
 *     after the confirmation
 *   - a failed save keeps the unsaved edits and shows an error
 *   - "Cancel" leaves the correction mode and restores the persisted values
 *   - "Reset My Scores" opens its own confirmation dialog
 *
 * Run with: node tests/Support/evaluate-correction-dom.test.mjs
 */
import { initEvaluation } from '../../resources/js/evaluate.js';

const results = {};
const assert = (condition, message) => {
    if (! condition) throw new Error(message);
};

const CRITERIA = [
    { id: '1', weight: 10 },
    { id: '2', weight: 10 },
    { id: '3', weight: 20 },
    { id: '4', weight: 20 },
    { id: '5', weight: 15 },
    { id: '6', weight: 25 },
];

const ATTRS = [
    'data-selected-score',
    'data-selected-label',
    'data-contribution-card',
    'data-summary-contribution',
    'data-summary-score',
];

function makeEl(text, classes = []) {
    const node = {
        _text: String(text),
        _classes: classes.slice(),
        disabled: false,
        listeners: {},
        addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
        fire(ev) {
            const event = { type: ev, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
            (this.listeners[ev] || []).forEach((fn) => fn.call(this, event));

            return event;
        },
        focus() {},
        submit() { node.submitted = true; },
        querySelectorAll() { return []; },
    };

    node.classList = {
        add(...names) {
            names.forEach((name) => {
                if (! node._classes.includes(name)) node._classes.push(name);
            });
        },
        remove(...names) {
            node._classes = node._classes.filter((name) => ! names.includes(name));
        },
        contains(name) { return node._classes.includes(name); },
        toggle(name, force) {
            const has = node._classes.includes(name);
            const next = force === undefined ? ! has : force;
            if (next) node.classList.add(name); else node.classList.remove(name);

            return next;
        },
    };

    Object.defineProperty(node, 'textContent', {
        get() { return this._text; },
        set(value) { this._text = String(value); },
        enumerable: true,
    });

    return node;
}

function buildDom() {
    const radios = [];
    CRITERIA.forEach((c) => {
        for (let i = 1; i <= 5; i++) {
            radios.push({
                dataset: { criterion: c.id },
                value: String(i),
                checked: false,
                disabled: true,
                listeners: {},
                addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
                fire(ev) { (this.listeners[ev] || []).forEach((fn) => fn.call(this)); },
            });
        }
    });

    const ids = {
        'autosave-status': makeEl('Submitted - no changes can be made.'),
        'total-preview': makeEl('82.00'),
        'total-label': makeEl('Weighted score preview (complete)'),
        'scored-count': makeEl('6'),
        'comment': makeEl(''),
        'submit-button': makeEl(''),
        'submit-hint': makeEl(''),
        'retry-save': makeEl(''),

        // Correction / reset interface
        'edit-my-scores': makeEl('Edit My Scores'),
        'reset-my-scores': makeEl('Reset My Scores'),
        'submitted-evaluation-actions': makeEl(''),
        'correction-form': makeEl('', ['hidden']),
        'cancel-correction': makeEl('Cancel'),
        'reset-my-scores-form': makeEl('', ['hidden']),
        'correction-message': makeEl('', ['hidden']),

        'edit-confirm-dialog': makeEl('', ['hidden']),
        'save-confirm-dialog': makeEl('', ['hidden']),
        'reset-confirm-dialog': makeEl('', ['hidden']),
        'edit-dialog-cancel': makeEl('Cancel'),
        'edit-dialog-confirm': makeEl('Yes, Edit Scores'),
        'save-dialog-cancel': makeEl('Cancel'),
        'save-dialog-confirm': makeEl('Yes, Save Corrections'),
        'reset-dialog-cancel': makeEl('Cancel'),
        'reset-dialog-confirm': makeEl('Yes, Reset'),
    };

    const textNodes = {};
    ATTRS.forEach((attr) => {
        textNodes['[' + attr + ']'] = {};
        CRITERIA.forEach((c) => {
            const el = makeEl('-');
            el.getAttribute = (name) => (name === attr ? c.id : null);
            textNodes['[' + attr + ']'][c.id] = [el];
        });
    });

    return { radios, ids, textNodes };
}

function installDom(dom) {
    const ALLOWED_SELECTORS = [
        /^\[data-criterion\]$/,
        /^\[data-[a-z-]+\]$/,
    ];

    global.document = {
        querySelectorAll(selector) {
            const allowed = ALLOWED_SELECTORS.some((re) => re.test(selector));
            if (! allowed) {
                throw new SyntaxError(
                    "Failed to execute 'querySelectorAll' on 'Document': '" + selector + "' is not a valid selector."
                );
            }

            if (selector === '[data-criterion]') return dom.radios;

            const m = /^(\[data-[a-z-]+\])$/.exec(selector);
            if (m) {
                const attr = m[1];
                return dom.textNodes[attr] ? Object.values(dom.textNodes[attr]).flat() : [];
            }

            return [];
        },
        querySelector(selector) {
            if (selector === 'meta[name="csrf-token"]') return { getAttribute: () => 'test-token' };
            return null;
        },
        getElementById(id) { return dom.ids[id] || null; },
    };

    global.window = {
        addEventListener() {},
        console: { error() {} },
        location: { reload() { dom.reloaded = true; } },
    };
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

// ------------------------------------------------------------------ scenario
const dom = buildDom();
installDom(dom);

let correctionRequest = null;
let correctionResponse = {
    ok: true,
    status: 200,
    json: {
        ok: true,
        status: 'submitted',
        scores: { 1: 5, 2: 4, 3: 4, 4: 3, 5: 4, 6: 5 },
        total_score: 89.0,
        saved_at: '2026-10-22T10:00:00+07:00',
    },
};

global.fetch = async (url, options) => {
    if (String(url).includes('/correct')) {
        correctionRequest = { url: String(url), body: JSON.parse(options.body) };

        return { ok: correctionResponse.ok, status: correctionResponse.status, json: async () => correctionResponse.json };
    }

    return { ok: true, status: 200, json: async () => ({}) };
};

initEvaluation({
    autosaveUrl: '/judge/category/1/finalist/1/autosave',
    correctionUrl: '/judge/category/1/finalist/1/correct',
    csrfToken: 'test-token',
    criteria: CRITERIA,
    readOnly: true,
    correctionMode: true,
});

const el = (id) => dom.ids[id];
const hidden = (id) => el(id).classList.contains('hidden');

// 1. the default state is read-only
results.defaultState = {
    radiosDisabled: dom.radios.every((r) => r.disabled),
    correctionFormHidden: hidden('correction-form'),
    actionsVisible: ! hidden('submitted-evaluation-actions'),
    editDialogHidden: hidden('edit-confirm-dialog'),
    saveDialogHidden: hidden('save-confirm-dialog'),
    resetDialogHidden: hidden('reset-confirm-dialog'),
};

assert(results.defaultState.radiosDisabled, 'the scores must be read-only by default');
assert(results.defaultState.correctionFormHidden, 'the correction form must be hidden by default');
assert(results.defaultState.actionsVisible, 'the read-only actions must be visible by default');
assert(results.defaultState.editDialogHidden, 'no dialog may be open by default');
assert(results.defaultState.saveDialogHidden, 'no dialog may be open by default');
assert(results.defaultState.resetDialogHidden, 'no dialog may be open by default');

// 2. "Edit My Scores" opens the confirmation dialog first
el('edit-my-scores').fire('click');
results.afterEditClick = {
    editDialogVisible: ! hidden('edit-confirm-dialog'),
    radiosDisabled: dom.radios.every((r) => r.disabled),
    correctionFormHidden: hidden('correction-form'),
};
assert(results.afterEditClick.editDialogVisible, 'the confirmation dialog must open');
assert(results.afterEditClick.radiosDisabled, 'the scores stay read-only until the dialog is confirmed');
assert(results.afterEditClick.correctionFormHidden, 'the correction form stays hidden until the dialog is confirmed');

// 3. cancelling the dialog changes nothing
el('edit-dialog-cancel').fire('click');
results.afterEditCancel = {
    editDialogHidden: hidden('edit-confirm-dialog'),
    radiosDisabled: dom.radios.every((r) => r.disabled),
    correctionFormHidden: hidden('correction-form'),
    actionsVisible: ! hidden('submitted-evaluation-actions'),
};
assert(results.afterEditCancel.editDialogHidden, 'the dialog must close again');
assert(results.afterEditCancel.radiosDisabled, 'cancelling must not enable the scores');
assert(results.afterEditCancel.correctionFormHidden, 'cancelling must not open the correction form');
assert(results.afterEditCancel.actionsVisible, 'cancelling must keep the read-only actions');

// 4. confirming the dialog enters the correction mode
el('edit-my-scores').fire('click');
el('edit-dialog-confirm').fire('click');
results.correctionMode = {
    radiosEnabled: dom.radios.every((r) => ! r.disabled),
    correctionFormVisible: ! hidden('correction-form'),
    actionsHidden: hidden('submitted-evaluation-actions'),
    status: el('autosave-status').textContent,
};
assert(results.correctionMode.radiosEnabled, 'the scores must be editable in the correction mode');
assert(results.correctionMode.correctionFormVisible, 'the correction form must be visible');
assert(results.correctionMode.actionsHidden, 'Edit My Scores and Reset My Scores must be hidden in the correction mode');
assert(results.correctionMode.status.includes('Correction mode'), 'the status must show the correction mode');

// 5. a score change is recalculated immediately
const radio = dom.radios.find((r) => r.dataset.criterion === '1' && r.value === '5');
dom.radios.filter((r) => r.dataset.criterion === '1').forEach((r) => { r.checked = false; });
radio.checked = true;
radio.fire('change');
results.afterScoreChange = {
    total: el('total-preview').textContent,
    status: el('autosave-status').textContent,
};
assert(results.afterScoreChange.status.includes('Unsaved corrections'), 'the status must show the pending correction');

// 6. "Save Corrections" opens the confirmation dialog and sends nothing yet
el('correction-form').fire('submit');
results.afterSaveClick = {
    saveDialogVisible: ! hidden('save-confirm-dialog'),
    requestSent: correctionRequest !== null,
};
assert(results.afterSaveClick.saveDialogVisible, 'the save confirmation dialog must open');
assert(results.afterSaveClick.requestSent === false, 'nothing may be sent before the confirmation');

// 7. cancelling the save dialog keeps the edits
el('save-dialog-cancel').fire('click');
results.afterSaveCancel = {
    saveDialogHidden: hidden('save-confirm-dialog'),
    requestSent: correctionRequest !== null,
    radiosEnabled: dom.radios.every((r) => ! r.disabled),
};
assert(results.afterSaveCancel.saveDialogHidden, 'the dialog must close');
assert(results.afterSaveCancel.requestSent === false, 'nothing may be sent when the dialog is cancelled');
assert(results.afterSaveCancel.radiosEnabled, 'cancelling the dialog must keep the edits');

// 8. confirming the dialog sends the correction
el('correction-form').fire('submit');
el('save-dialog-confirm').fire('click');
await wait(50);
results.afterConfirmedSave = {
    requestSent: correctionRequest !== null,
    score: correctionRequest ? correctionRequest.body.scores['1'] : null,
    radiosDisabled: dom.radios.every((r) => r.disabled),
    correctionFormHidden: hidden('correction-form'),
    actionsVisible: ! hidden('submitted-evaluation-actions'),
    message: el('correction-message').textContent,
    messageHidden: hidden('correction-message'),
    status: el('autosave-status').textContent,
};
assert(results.afterConfirmedSave.requestSent, 'the correction must be sent after the confirmation');
assert(results.afterConfirmedSave.score === 5, 'the corrected score must be sent');
assert(results.afterConfirmedSave.radiosDisabled, 'the page must return to the read-only mode');
assert(results.afterConfirmedSave.correctionFormHidden, 'the correction form must be hidden again');
assert(results.afterConfirmedSave.actionsVisible, 'the read-only actions must be available again');
assert(results.afterConfirmedSave.message.includes('corrections have been saved'), 'a success message must be shown');
assert(results.afterConfirmedSave.messageHidden === false, 'the success message must be visible');
assert(results.afterConfirmedSave.status.includes('Submitted at'), 'the saved timestamp must be shown');

// 9. a failed save keeps the unsaved edits and shows an error
el('edit-my-scores').fire('click');
el('edit-dialog-confirm').fire('click');
const radio2 = dom.radios.find((r) => r.dataset.criterion === '3' && r.value === '3');
dom.radios.filter((r) => r.dataset.criterion === '3').forEach((r) => { r.checked = false; });
radio2.checked = true;
radio2.fire('change');

correctionResponse = { ok: false, status: 500, json: { ok: false, message: 'The corrections could not be saved.' } };
el('correction-form').fire('submit');
el('save-dialog-confirm').fire('click');
await wait(50);
results.afterFailedSave = {
    radiosEnabled: dom.radios.every((r) => ! r.disabled),
    message: el('correction-message').textContent,
    messageHidden: hidden('correction-message'),
    actionsHidden: hidden('submitted-evaluation-actions'),
};
assert(results.afterFailedSave.radiosEnabled, 'a failed save must keep the unsaved edits');
assert(results.afterFailedSave.message.includes('Save failed'), 'a failed save must show an error message');
assert(results.afterFailedSave.messageHidden === false, 'the error message must be visible');
assert(results.afterFailedSave.actionsHidden, 'the correction mode must stay active after a failed save');

// 10. cancelling the correction leaves the correction mode
correctionResponse = { ok: true, status: 200, json: { ok: true, scores: {}, total_score: 0, saved_at: '2026-10-22T10:00:00+07:00' } };
el('cancel-correction').fire('click');
results.afterCancelCorrection = { reloaded: dom.reloaded === true };
assert(results.afterCancelCorrection.reloaded, 'cancelling must restore the persisted values (reload)');

// 11. the reset opens its own confirmation dialog
el('reset-my-scores').fire('click');
results.afterResetClick = {
    resetDialogVisible: ! hidden('reset-confirm-dialog'),
};
assert(results.afterResetClick.resetDialogVisible, 'the reset confirmation dialog must open');

console.log(JSON.stringify({ ok: true, results }));
