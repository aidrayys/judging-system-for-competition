/**
 * Node test for the pure frontend scoring helpers.
 * Run with: node tests/Support/frontend-scoring.test.mjs
 *
 * The output is a single JSON document so that PHPUnit can assert on it.
 */
import {
    buildPayload,
    collectScores,
    computeSummary,
    contributionFor,
    isValidScore,
    labelForScore,
} from '../../resources/js/evaluation-scoring.js';

const results = {};
const assert = (condition, message) => {
    if (! condition) {
        throw new Error(message);
    }
};

const CRITERIA = [
    { id: '1', weight: 10, name: 'Background & Method' },
    { id: '2', weight: 10, name: 'Content & Presentation' },
    { id: '3', weight: 20, name: 'Collaboration (Scope)' },
    { id: '4', weight: 20, name: 'Impact' },
    { id: '5', weight: 15, name: 'Implementation' },
    { id: '6', weight: 25, name: 'Solution' },
];

/** Build radio inputs like the Blade markup does (5 per criterion, scale 1..5). */
function radios(selection) {
    const list = [];
    CRITERIA.forEach((c) => {
        for (let i = 1; i <= 5; i++) {
            list.push({
                dataset: { criterion: c.id },
                value: String(i),
                checked: selection[c.id] === i,
            });
        }
    });
    return list;
}

// 1. collectScores must keep the checked radio of every group
const collected = collectScores(radios({ 1: 4, 3: 2 }));
results.collectScores = collected;
assert(collected['1'] === 4, 'criterion 1 must keep the checked score');
assert(collected['3'] === 2, 'criterion 3 must keep the checked score');
assert(collected['2'] === null, 'unselected criterion must be null');
assert(Object.keys(collected).length === 6, 'all six criteria must be present');

// 2. summary updates immediately and only counts selected criteria
const summary = computeSummary(CRITERIA, collected);
results.summaryAfterTwoClicks = {
    total: summary.total,
    scoredCount: summary.scoredCount,
    criteriaCount: summary.criteriaCount,
    provisional: summary.provisional,
    complete: summary.complete,
    contributions: summary.rows.map((r) => r.contribution),
    selectedScores: summary.rows.map((r) => r.score),
};

assert(summary.scoredCount === 2, 'exactly two criteria are scored');
assert(summary.provisional === true, 'an incomplete evaluation is provisional');
assert(summary.complete === false, 'an incomplete evaluation is not complete');
// 4/5*10 + 2/5*20 = 8 + 8 = 16
assert(Math.abs(summary.total - 16) < 0.001, 'total must be 16, got ' + summary.total);
assert(summary.rows[0].score === 4, 'row 1 must show the selected score');
assert(summary.rows[1].score === null, 'row 2 must show no score');
assert(summary.rows[1].contribution === 0, 'an unselected criterion must not contribute');

// 3. full evaluation
const full = collectScores(radios({ 1: 5, 2: 5, 3: 5, 4: 5, 5: 5, 6: 5 }));
const fullSummary = computeSummary(CRITERIA, full);
results.summaryAllTen = {
    total: fullSummary.total,
    scoredCount: fullSummary.scoredCount,
    complete: fullSummary.complete,
    provisional: fullSummary.provisional,
    contributions: fullSummary.rows.map((r) => r.contribution),
};
assert(fullSummary.total === 100, 'all fives must total 100, got ' + fullSummary.total);
assert(fullSummary.complete === true, 'all six scored must be complete');
assert(fullSummary.provisional === false, 'a complete evaluation is not provisional');
assert(fullSummary.rows.map((r) => r.contribution).join(',') === '10,10,20,20,15,25', 'contributions must match the weights');

// 4. the formula
assert(contributionFor(10, 5) === 10, 'score 5 on a 10% criterion contributes 10');
assert(contributionFor(25, 5) === 25, 'score 5 on a 25% criterion contributes 25');
assert(contributionFor(20, 3) === 12, 'score 3 on a 20% criterion contributes 12');
assert(contributionFor(20, 10) === 0, 'a legacy 1..10 value above 5 contributes nothing');
assert(contributionFor(15, 0) === 0, 'an invalid score contributes nothing');

// 5. validity
results.invalidScores = [0, 6, 10, 11, 13, -1, 2.5, 'abc'].map((v) => ({
    value: v,
    valid: isValidScore(v),
}));
assert(isValidScore(1) && isValidScore(5), '1 and 5 are valid');
assert(! isValidScore(6), '6 is invalid');
assert(! isValidScore(10), '10 is invalid');
assert(! isValidScore(0), '0 is invalid');
assert(! isValidScore(11), '11 is invalid');
assert(! isValidScore(13), '13 is invalid');
assert(! isValidScore(2.5), '2.5 is invalid');

// 6. labels
results.labels = [1, 2, 3, 4, 5, 6].map((s) => ({ score: s, label: labelForScore(s) }));
assert(labelForScore(1) === 'Very Poor', '1 is Very Poor');
assert(labelForScore(2) === 'Poor', '2 is Kurang');
assert(labelForScore(3) === 'Fair', '3 is Cukup');
assert(labelForScore(4) === 'Good', '4 is Baik');
assert(labelForScore(5) === 'Very Good', '5 is Very Good');
assert(labelForScore(6) === '', '6 is outside the scale');

// 7. payload keeps nulls (so the server can clear) and never sends invalid values
const payload = buildPayload({ 1: 4, 2: null, 3: 6, 4: 2 }, 'hello');
results.payload = payload;
assert(payload.scores['1'] === 4, 'valid score must be sent');
assert(payload.scores['2'] === null, 'cleared score must be sent as null');
assert(payload.scores['3'] === null, 'an invalid score must never be sent as a number');
assert(payload.scores['4'] === 2, 'valid score must be sent');
assert(payload.comment === 'hello', 'comment must be sent');

console.log(JSON.stringify({ ok: true, results }));
