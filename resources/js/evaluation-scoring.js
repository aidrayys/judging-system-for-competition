/**
 * Pure scoring helpers for the judge evaluation page.
 *
 * This module contains no DOM access so that it can be unit tested
 * (see tests/Support/frontend-scoring.test.mjs).
 *
 * Formula (identical to App\Services\ScoringService):
 *   contribution = (score / MAX_SCORE) * weight   (scale 1..5)
 *   total        = sum of all contributions
 * The weights are stored as percentages, so a score of 5 on a 10%
 * criterion contributes 10 points and the maximum total is 100.
 */

export const MIN_SCORE = 1;
export const MAX_SCORE = 5;

export const INTERPRETATION_GUIDE = [
    { min: 1, max: 1, range: '1', label: 'Very Poor' },
    { min: 2, max: 2, range: '2', label: 'Poor' },
    { min: 3, max: 3, range: '3', label: 'Fair' },
    { min: 4, max: 4, range: '4', label: 'Good' },
    { min: 5, max: 5, range: '5', label: 'Very Good' },
];

export function isValidScore(value) {
    if (value === null || value === undefined || value === '') return false;
    const num = Number(value);
    return Number.isInteger(num) && num >= MIN_SCORE && num <= MAX_SCORE;
}

export function labelForScore(value) {
    if (!isValidScore(value)) return '';
    const score = Number(value);
    const band = INTERPRETATION_GUIDE.find((b) => score >= b.min && score <= b.max);
    return band ? band.label : '';
}

export function contributionFor(weight, score) {
    if (!isValidScore(score)) return 0;
    return (Number(score) / MAX_SCORE) * Number(weight);
}

/**
 * Collect the selected score per criterion from a list of radio inputs.
 *
 * A radio group holds one input per possible value (1..5). Only the
 * checked input of a group carries a selection - every unchecked input of
 * the same group must be ignored, otherwise the last (unchecked) input
 * would overwrite the real selection.
 *
 * @param {Array<{dataset: {criterion: string}, checked: boolean, value: string}>} radios
 * @returns {Object<string, number|null>} criterion id => score (null when unselected)
 */
export function collectScores(radios) {
    const scores = {};

    radios.forEach((radio) => {
        const id = radio.dataset.criterion;
        if (id === undefined) return;

        if (!(id in scores)) {
            scores[id] = null;
        }

        if (radio.checked) {
            const value = Number(radio.value);
            scores[id] = isValidScore(value) ? value : null;
        }
    });

    return scores;
}

/**
 * Build the autosave payload. Every criterion is included so that the
 * server can also clear a score, but invalid values are never sent.
 *
 * @param {Object<string, number|null>} scores
 */
export function buildPayload(scores, comment) {
    const payload = {};

    Object.keys(scores).forEach((id) => {
        const value = scores[id];
        payload[id] = isValidScore(value) ? Number(value) : null;
    });

    return { scores: payload, comment: comment || '' };
}

/**
 * Calculate the complete evaluation summary.
 *
 * @param {Array<{id: string|number, weight: number, name?: string}>} criteria
 * @param {Object<string, number|null>} scores
 */
export function computeSummary(criteria, scores) {
    const rows = [];
    let total = 0;
    let scoredCount = 0;

    criteria.forEach((criterion) => {
        const id = String(criterion.id);
        const score = scores[id] === undefined ? null : scores[id];
        const valid = isValidScore(score);
        const contribution = contributionFor(criterion.weight, valid ? Number(score) : null);

        if (valid) scoredCount += 1;
        total += contribution;

        rows.push({
            id,
            name: criterion.name || '',
            weight: Number(criterion.weight),
            score: valid ? Number(score) : null,
            label: valid ? labelForScore(score) : '',
            contribution: Math.round(contribution * 100) / 100,
        });
    });

    const totalRounded = Math.round(total * 100) / 100;
    const criteriaCount = criteria.length;

    return {
        rows,
        total: totalRounded,
        scoredCount,
        criteriaCount,
        complete: criteriaCount > 0 && scoredCount === criteriaCount,
        provisional: criteriaCount > 0 && scoredCount !== criteriaCount,
    };
}
