/**
 * Regression: edits made WHILE an autosave request is in flight must not be
 * lost nor reported as "Saved" before the server confirms them.
 * Run with: node tests/Support/evaluate-inflight-dom.test.mjs
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

function makeEl(text) {
    const node = {
        _text: String(text),
        className: '',
        disabled: false,
        listeners: {},
        addEventListener(ev, fn) { (this.listeners[ev] ||= []).push(fn); },
    };
    // The DOM always coerces textContent to a string.
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
        'submit-button': makeEl(''),
        'submit-hint': makeEl(''),
        'retry-save': makeEl(''),
    };

    // keyed exactly like the selectors the module builds: "[attr]"
    const textNodes = {};
    ATTRS.forEach((attr) => {
        textNodes['[' + attr + ']'] = {};
        CRITERIA.forEach((c) => {
            const el = makeEl('-');
            // the DOM element knows the value of its data attribute
            el.getAttribute = (name) => (name === attr ? c.id : null);
            textNodes['[' + attr + ']'][c.id] = [el];
        });
    });
    return { radios, ids, textNodes };
}

function installDom(dom) {
    // The shim only accepts a small set of known selectors. Anything else
    // throws exactly like a browser would, so an invalid selector can never
    // pass unnoticed again.
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
    global.window = { addEventListener() {}, console: { error() {} } };
}

function click(dom, criterionId, value) {
    const radio = dom.radios.find((r) => r.dataset.criterion === criterionId && r.value === String(value));
    dom.radios.filter((r) => r.dataset.criterion === criterionId).forEach((r) => { r.checked = false; });
    radio.checked = true;
    radio.fire('change');
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));


const dom = buildDom();
installDom(dom);

const requests = [];
let release = null;            // resolves the pending fetch
global.fetch = (url, options) => {
    const body = JSON.parse(options.body);
    requests.push(body);
    return new Promise((resolve) => {
        release = () => resolve({
            ok: true, status: 200,
            json: async () => ({ scores: Object.fromEntries(Object.entries(body.scores).filter(([, v]) => v !== null)), saved_at: '2026-01-01T09:00:00+07:00' }),
        });
    });
};

initEvaluation({ autosaveUrl: '/x/autosave', csrfToken: 't', criteria: CRITERIA, readOnly: false, lastSavedAt: null });
results.initialStatus = dom.ids['autosave-status'].textContent;
assert(results.initialStatus === 'No changes yet', 'fresh evaluation status, got ' + results.initialStatus);

click(dom, '1', 5);
await wait(1300);                          // request #1 now in flight
assert(requests.length === 1, 'first request sent');
assert(dom.ids['autosave-status'].textContent === 'Saving...', 'Saving... while in flight');

// Judge keeps working during the request.
click(dom, '2', 3);
dom.ids['comment'].value = 'typed during save';
await wait(1300);                          // debounce fires, but #1 still pending
assert(requests.length === 1, 'must not send concurrently');

release();                                  // server confirms #1 only
await wait(50);
results.afterFirstConfirm = dom.ids['autosave-status'].textContent;
assert(! results.afterFirstConfirm.startsWith('Saved'), 'must NOT claim Saved for unsent edits, got ' + results.afterFirstConfirm);
assert(dom.ids['submit-button'].disabled === true, 'submit disabled while edits unsaved');

await wait(1300);                          // follow-up save is sent automatically
assert(requests.length === 2, 'pending edits must be re-sent, got ' + requests.length);
assert(requests[1].scores['2'] === 3, 'second request carries the in-flight score');
assert(requests[1].comment === 'typed during save', 'second request carries the in-flight comment');
release();
await wait(50);
results.afterSecondConfirm = dom.ids['autosave-status'].textContent;
assert(results.afterSecondConfirm.startsWith('Saved at'), 'Saved only after confirmation, got ' + results.afterSecondConfirm);

// Status on reload reflects DB state.
const dom2 = buildDom();
installDom(dom2);
initEvaluation({ autosaveUrl: '/x', csrfToken: 't', criteria: CRITERIA, readOnly: true, submitted: true });
results.submittedStatus = dom2.ids['autosave-status'].textContent;
assert(results.submittedStatus.startsWith('Submitted / Locked'), 'locked status, got ' + results.submittedStatus);

const dom3 = buildDom();
installDom(dom3);
initEvaluation({ autosaveUrl: '/x', csrfToken: 't', criteria: CRITERIA, readOnly: false, lastSavedAt: '2026-01-01T09:00:00+07:00' });
results.draftStatus = dom3.ids['autosave-status'].textContent;
assert(results.draftStatus.startsWith('Saved at'), 'draft loaded from DB shows Saved, got ' + results.draftStatus);

console.log(JSON.stringify({ ok: true, results }));
