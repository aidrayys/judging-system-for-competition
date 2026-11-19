/**
 * Node test for the submission confirmation of the evaluation page
 * (resources/js/evaluate.js).
 *
 * A minimal DOM shim is used so the real module can run head-less. The test
 * proves that the browser native confirm() dialog is replaced by the custom
 * Judging System for Competition modal:
 *
 *   - clicking "Submit Evaluation" opens the modal and sends nothing
 *   - "Cancel" closes the modal without submitting
 *   - "Yes, Submit" sends the form exactly once and shows the loading state
 *   - a second click cannot send a second request
 *
 * Run with: node tests/Support/evaluate-submit-dom.test.mjs
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
        _label: null,
        disabled: false,
        submitted: 0,
        listeners: {},
        addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
        fire(ev) {
            const event = { type: ev, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
            (this.listeners[ev] || []).forEach((fn) => fn.call(this, event));

            return event;
        },
        focus() {},
        submit() { node.submitted += 1; },
        querySelectorAll() { return []; },
        querySelector(selector) {
            if (selector === '[data-submit-label]') {
                if (! node._label) node._label = makeEl(node._text);

                return node._label;
            }

            return null;
        },
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
                disabled: false,
                listeners: {},
                addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
                fire(ev) { (this.listeners[ev] || []).forEach((fn) => fn.call(this)); },
            });
        }
    });

    const ids = {
        'autosave-status': makeEl('Idle'),
        'total-preview': makeEl('0.00'),
        'total-label': makeEl('Weighted score preview'),
        'scored-count': makeEl('0'),
        'comment': makeEl(''),
        'submit-button': makeEl('Submit Evaluation'),
        'submit-hint': makeEl(''),
        'retry-save': makeEl(''),

        'submit-form': makeEl(''),
        'submit-confirm-dialog': makeEl('', ['hidden']),
        'submit-dialog-cancel': makeEl('Cancel'),
        'submit-dialog-confirm': makeEl('Yes, Submit'),
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

let requests = 0;
global.fetch = async () => {
    requests += 1;

    return { ok: true, status: 200, json: async () => ({}) };
};

initEvaluation({
    autosaveUrl: '/judge/category/1/finalist/1/autosave',
    csrfToken: 'test-token',
    criteria: CRITERIA,
    readOnly: false,
});

const el = (id) => dom.ids[id];
const hidden = (id) => el(id).classList.contains('hidden');

// 1. clicking the submit button opens the custom modal and sends nothing
const event = el('submit-form').fire('submit');
results.afterSubmitClick = {
    dialogVisible: ! hidden('submit-confirm-dialog'),
    prevented: event.defaultPrevented,
    submissions: el('submit-form').submitted,
};
assert(results.afterSubmitClick.dialogVisible, 'the custom modal must open');
assert(results.afterSubmitClick.prevented, 'the form must not be sent before the confirmation');
assert(results.afterSubmitClick.submissions === 0, 'nothing may be sent before the confirmation');

// 2. cancelling closes the modal and does not submit
el('submit-dialog-cancel').fire('click');
const submitLabel = () => el('submit-button').querySelector('[data-submit-label]').textContent;
results.afterCancel = {
    dialogHidden: hidden('submit-confirm-dialog'),
    submissions: el('submit-form').submitted,
    label: submitLabel(),
};
assert(results.afterCancel.dialogHidden, 'the modal must close');
assert(results.afterCancel.submissions === 0, 'cancelling must not submit');
assert(results.afterCancel.label === 'Submit Evaluation', 'cancelling must not change the submit button');

// 3. confirming sends the form exactly once and shows the loading state
el('submit-form').fire('submit');
el('submit-dialog-confirm').fire('click');
results.afterConfirm = {
    submissions: el('submit-form').submitted,
    confirmDisabled: el('submit-dialog-confirm').disabled,
    confirmLabel: el('submit-dialog-confirm').textContent,
    cancelDisabled: el('submit-dialog-cancel').disabled,
    submitButtonDisabled: el('submit-button').disabled,
    label: submitLabel(),
};
assert(results.afterConfirm.submissions === 1, 'the form must be sent exactly once');
assert(results.afterConfirm.confirmDisabled, 'the confirmation button must be disabled');
assert(results.afterConfirm.confirmLabel.includes('Submitting'), 'the loading label must be shown');
assert(results.afterConfirm.cancelDisabled, 'the cancel button must be disabled');
assert(results.afterConfirm.submitButtonDisabled, 'the submit button must be disabled');
assert(results.afterConfirm.label === 'Submitting...', 'the submit button must show the loading state');

// 4. a second click cannot send a second request
el('submit-dialog-confirm').fire('click');
el('submit-dialog-confirm').fire('click');
results.afterRepeatedClick = { submissions: el('submit-form').submitted };
assert(results.afterRepeatedClick.submissions === 1, 'a repeated click must not submit twice');

console.log(JSON.stringify({ ok: true, results }));
