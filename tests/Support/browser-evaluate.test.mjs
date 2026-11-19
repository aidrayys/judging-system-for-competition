/**
 * Real-browser end-to-end check of the evaluation page (Chrome DevTools Protocol).
 *
 *   1. log in and open the evaluation page
 *   2. click a score button and read the summary IMMEDIATELY (before the
 *      network request completes)
 *   3. wait for the autosave and read the status
 *   4. reload the page and read the values again
 *
 * The result is printed as JSON so that PHPUnit can assert on it.
 *
 * Usage: node browser-evaluate.test.mjs <baseUrl> <username> <password>
 */
import { spawn } from 'child_process';
import { mkdtempSync, rmSync } from 'fs';
import { tmpdir } from 'os';
import { join } from 'path';

const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const USERNAME = process.argv[3] || 'browserjudge';
const PASSWORD = process.argv[4] || 'BrowserTest123!';

const CHROME_CANDIDATES = [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
];

import { existsSync } from 'fs';
const CHROME = CHROME_CANDIDATES.find((p) => existsSync(p));
if (! CHROME) {
    console.log(JSON.stringify({ ok: false, skipped: 'no chrome binary found' }));
    process.exit(0);
}

const PORT = 9400 + Math.floor(Math.random() * 400);
const profile = mkdtempSync(join(tmpdir(), 'jc-chrome-'));

const chrome = spawn(CHROME, [
    '--headless=new',
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${profile}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-gpu',
    '--window-size=1400,1200',
    'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function cleanup() {
    try { chrome.kill(); } catch (e) { /* ignore */ }
    try { rmSync(profile, { recursive: true, force: true }); } catch (e) { /* ignore */ }
}

async function waitForDevtools() {
    for (let i = 0; i < 80; i++) {
        try {
            const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
            if (res.ok) return;
        } catch (e) { /* not ready */ }
        await sleep(250);
    }
    throw new Error('The Chrome DevTools endpoint did not start');
}

function connect(wsUrl) {
    return new Promise((resolve, reject) => {
        const ws = new WebSocket(wsUrl);
        let id = 0;
        const pending = new Map();
        const listeners = [];
        ws.addEventListener('message', (ev) => {
            const msg = JSON.parse(ev.data);
            if (msg.id && pending.has(msg.id)) {
                const p = pending.get(msg.id);
                pending.delete(msg.id);
                msg.error ? p.reject(new Error(JSON.stringify(msg.error))) : p.resolve(msg.result);
            } else if (msg.method) {
                listeners.forEach((l) => l(msg));
            }
        });
        ws.addEventListener('open', () => resolve({
            send: (method, params = {}) => new Promise((res, rej) => {
                const msgId = ++id;
                pending.set(msgId, { resolve: res, reject: rej });
                ws.send(JSON.stringify({ id: msgId, method, params }));
            }),
            on: (fn) => listeners.push(fn),
            close: () => ws.close(),
        }));
        ws.addEventListener('error', reject);
    });
}

try {
    await waitForDevtools();

    const targets = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
    const cdp = await connect(targets.find((t) => t.type === 'page').webSocketDebuggerUrl);

    const exceptions = [];
    const consoleLines = [];
    const autosaves = [];

    cdp.on((m) => {
        if (m.method === 'Runtime.exceptionThrown') {
            const d = m.params.exceptionDetails;
            exceptions.push(`${d.text} ${d.exception ? (d.exception.description || '') : ''}`);
        }
        if (m.method === 'Runtime.consoleAPICalled') {
            consoleLines.push(`[${m.params.type}] ` + (m.params.args || []).map((a) => a.value ?? a.description ?? '').join(' '));
        }
        if (m.method === 'Network.requestWillBeSent' && m.params.request.url.includes('/autosave')) {
            autosaves.push({ url: m.params.request.url, method: m.params.request.method, body: m.params.request.postData });
        }
        if (m.method === 'Network.responseReceived' && m.params.response.url.includes('/autosave')) {
            autosaves.push({ status: m.params.response.status });
        }
    });

    await cdp.send('Runtime.enable');
    await cdp.send('Page.enable');
    await cdp.send('Network.enable');

    const evaluate = async (expression) => {
        const res = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (res.exceptionDetails) {
            throw new Error('EVAL: ' + JSON.stringify(res.exceptionDetails.exception || res.exceptionDetails.text));
        }
        return res.result.value;
    };

    const goto = async (url) => {
        await cdp.send('Page.navigate', { url });
        await sleep(1500);
    };

    /** Poll until the expression returns a truthy value (the dev server is slow). */
    const waitFor = async (expression, label, timeoutMs = 30000) => {
        const deadline = Date.now() + timeoutMs;
        let last = null;
        while (Date.now() < deadline) {
            last = await evaluate(expression);
            if (last) {
                return last;
            }
            await sleep(500);
        }
        throw new Error('Timed out waiting for ' + label + ' (last value: ' + JSON.stringify(last) + ')');
    };

    const snapshot = () => evaluate(`(() => ({
        status: document.getElementById('autosave-status') ? document.getElementById('autosave-status').textContent.trim() : null,
        selected: Array.from(document.querySelectorAll('[data-selected-score]')).map(e => e.textContent.trim()).join(','),
        contributions: Array.from(document.querySelectorAll('[data-contribution-card]')).map(e => e.textContent.trim()).join(','),
        summaryContributions: Array.from(document.querySelectorAll('[data-summary-contribution]')).map(e => e.textContent.trim()).join(','),
        count: document.getElementById('scored-count') ? document.getElementById('scored-count').textContent.trim() : null,
        total: document.getElementById('total-preview') ? document.getElementById('total-preview').textContent.trim() : null,
        submitDisabled: document.getElementById('submit-button') ? document.getElementById('submit-button').disabled : null,
    }))()`);

    // 1. login
    await goto(BASE + '/login');
    const loginFormPresent = await evaluate(`!!document.querySelector('input[name="username"]')`);
    if (! loginFormPresent) {
        const body = await evaluate('document.body ? document.body.innerText.slice(0, 900) : "no body"');
        throw new Error('login form not found. Page says: ' + body);
    }
    await evaluate(`(() => {
        const u = document.querySelector('input[name="username"]');
        const p = document.querySelector('input[name="password"]');
        if (! u || ! p) throw new Error('login form not found');
        u.value = ${JSON.stringify(USERNAME)};
        p.value = ${JSON.stringify(PASSWORD)};
        document.querySelector('form').submit();
        return true;
    })()`);
    await sleep(500);

    await waitFor(`window.location.pathname !== '/login' ? window.location.pathname : null`, 'the login redirect');
    await waitFor(`!!document.querySelector('a[href*="/judge/category/"]')`, 'the evaluation link on the judge dashboard');

    // 2. open the evaluation page (first finalist of the first category)
    const target = await evaluate(`(() => {
        const link = document.querySelector('a[href*="/judge/category/"]');
        return link ? link.getAttribute('href') : null;
    })()`);
    if (! target) {
        throw new Error('no evaluation link found on the judge dashboard');
    }
    const evaluationUrl = /^https?:\/\//i.test(target) ? target : BASE + target;
    await goto(evaluationUrl);
    await waitFor(`document.querySelectorAll('[data-criterion]').length > 0`, 'the score buttons');

    const before = await snapshot();

    // 3. click score 4 on every criterion
    const criterionIds = await evaluate(`(() => Array.from(new Set(Array.from(document.querySelectorAll('[data-criterion]')).map(e => e.dataset.criterion))).join(','))()`);
    for (const id of criterionIds.split(',')) {
        await evaluate(`document.getElementById('score-${id}-4').click()`);
    }

    // read the summary immediately - the request has not completed yet
    const immediately = await snapshot();

    // 4. wait for the debounced autosave (the result is read as soon as the
    // request finished, so a slow machine cannot make the test flaky)
    await waitFor(`(() => {
        const el = document.getElementById('autosave-status');
        if (! el) return null;
        const text = el.textContent.trim();
        return (text.startsWith('Saved') || text.startsWith('Save failed')) ? text : null;
    })()`, 'the autosave result');
    const afterSave = await snapshot();

    // 5. reload: the saved values must come back from the database
    await goto(evaluationUrl);
    await waitFor(`document.querySelectorAll('[data-criterion]').length > 0`, 'the score buttons after the reload');
    const afterReload = await snapshot();

    console.log(JSON.stringify({
        ok: true,
        result: { before, immediately, afterSave, afterReload, autosaves, exceptions, console: consoleLines },
    }, null, 2));

    cdp.close();
    cleanup();
    process.exit(0);
} catch (e) {
    console.log(JSON.stringify({ ok: false, error: e.message }, null, 2));
    cleanup();
    process.exit(1);
}
