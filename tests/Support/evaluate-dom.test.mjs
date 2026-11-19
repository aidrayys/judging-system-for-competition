/**
 * Node test for the evaluation page wiring (resources/js/evaluate.js).
 *
 * A minimal DOM shim is used so the real module can run head-less:
 *  - clicking a score button must update the summary immediately
 *  - the submit button stays disabled until all six criteria are scored
 *    AND the state has been saved successfully
 *  - a failing save must keep the selections and report "Save failed"
 *  - a successful save must not reset the judge's selections
 *
 * Run with: node tests/Support/evaluate-dom.test.mjs
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

// ------------------------------------------------------------------ scenario
const dom = buildDom();
installDom(dom);

let requestBody = null;
let respondWith = {
    ok: true,
    status: 200,
    json: { scores: {}, contributions: {}, total_score: 0, preview_total: 0, scored_count: 0, criteria_count: 6, saved_at: 'now' },
};
let failNetwork = false;

global.fetch = async (url, options) => {
    requestBody = JSON.parse(options.body);
    if (failNetwork) throw new TypeError('Failed to fetch');
    return { ok: respondWith.ok, status: respondWith.status, json: async () => respondWith.json };
};

initEvaluation({
    autosaveUrl: '/judge/category/1/finalist/1/autosave',
    csrfToken: 'test-token',
    criteria: CRITERIA,
    readOnly: false,
});

const node = (attr, id) => dom.textNodes['[' + attr + ']'][id][0].textContent;

results.initial = {
    scoredCount: dom.ids['scored-count'].textContent,
    total: dom.ids['total-preview'].textContent,
    submitDisabled: dom.ids['submit-button'].disabled,
};

// 1. clicking a score updates the summary immediately
click(dom, '1', 4);
click(dom, '3', 5);
results.afterTwoClicks = {
    scoredCount: dom.ids['scored-count'].textContent,
    total: dom.ids['total-preview'].textContent,
    label: dom.ids['total-label'].textContent,
    crit1Score: node('data-selected-score', '1'),
    crit1Contribution: node('data-contribution-card', '1'),
    crit2Score: node('data-selected-score', '2'),
    crit3Contribution: node('data-contribution-card', '3'),
    submitDisabled: dom.ids['submit-button'].disabled,
    status: dom.ids['autosave-status'].textContent,
};

assert(dom.ids['scored-count'].textContent === '2', 'counter must update immediately');
// criterion 1: 4/5*10 = 8   +   criterion 3: 5/5*20 = 20  =>  28.00
assert(dom.ids['total-preview'].textContent === '28.00', 'total must update immediately, got ' + dom.ids['total-preview'].textContent);
assert(node('data-selected-score', '1') === '4', 'selected score must be shown');
assert(node('data-contribution-card', '1') === '8.00', 'contribution must be shown');
assert(node('data-selected-score', '2') === '-', 'unselected criterion must show "-"');
assert(node('data-contribution-card', '3') === '20.00', 'criterion 3 contributes 20');
assert(dom.ids['total-label'].textContent.includes('Provisional'), 'incomplete total must be labelled provisional');
assert(dom.ids['submit-button'].disabled === true, 'submit must stay disabled while incomplete');

// 2. the payload contains the real selections
await wait(1500);
results.payloadAfterClicks = requestBody;
assert(requestBody !== null, 'an autosave request must be sent');
assert(requestBody.scores['1'] === 4, 'the payload must contain the selected score');
assert(requestBody.scores['3'] === 5, 'the payload must contain every selected score');
assert(requestBody.scores['2'] === null, 'unselected criteria are sent as null');

// 3. a failed save keeps the selections and reports the failure
failNetwork = true;
click(dom, '5', 3);
await wait(1500);
results.afterNetworkFailure = {
    status: dom.ids['autosave-status'].textContent,
    scoredCount: dom.ids['scored-count'].textContent,
    crit5Score: node('data-selected-score', '5'),
    total: dom.ids['total-preview'].textContent,
    submitDisabled: dom.ids['submit-button'].disabled,
};
assert(dom.ids['autosave-status'].textContent.startsWith('Save failed'), 'a failed save must be reported');
assert(node('data-selected-score', '5') === '3', 'the selection must survive a failed save');
assert(dom.ids['scored-count'].textContent === '3', 'the counter must keep the selections');
assert(dom.ids['submit-button'].disabled === true, 'submit must stay disabled when the save failed');

// 4. an HTTP error (e.g. closed judging period) is reported with the server message
failNetwork = false;
respondWith = { ok: false, status: 403, json: { message: 'The judging period is currently closed.' } };
click(dom, '6', 4);
await wait(1500);
results.afterHttpError = { status: dom.ids['autosave-status'].textContent };
assert(dom.ids['autosave-status'].textContent.includes('judging period'), 'the server message must be shown');

// 5. complete the evaluation and save it -> submit becomes enabled
respondWith = {
    ok: true,
    status: 200,
    json: {
        scores: { 1: 4, 2: 4, 3: 5, 4: 4, 5: 3, 6: 4 },
        contributions: { 1: 8, 2: 8, 3: 20, 4: 16, 5: 9, 6: 20 },
        total_score: 81,
        preview_total: 81,
        scored_count: 6,
        criteria_count: 6,
        saved_at: '2026-01-01 09:00:00',
    },
};
click(dom, '2', 4);
click(dom, '4', 4);
click(dom, '6', 4);
await wait(1500);
results.afterCompleteSave = {
    status: dom.ids['autosave-status'].textContent,
    scoredCount: dom.ids['scored-count'].textContent,
    total: dom.ids['total-preview'].textContent,
    submitDisabled: dom.ids['submit-button'].disabled,
    hint: dom.ids['submit-hint'].textContent,
    crit2Score: node('data-selected-score', '2'),
};
assert(dom.ids['scored-count'].textContent === '6', 'all six criteria must be counted');
assert(dom.ids['total-preview'].textContent === '81.00', 'the server total must be displayed, got ' + dom.ids['total-preview'].textContent);
assert(dom.ids['submit-button'].disabled === false, 'submit must be enabled after a successful save');
assert(node('data-selected-score', '2') === '4', 'a successful save must not reset the selections');

console.log(JSON.stringify({ ok: true, results }));
