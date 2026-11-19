<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Runs the real frontend scoring modules (resources/js/evaluation-scoring.js
 * and resources/js/evaluate.js) head-less through Node and asserts their
 * behaviour. The same formula is implemented on the server in
 * App\Services\ScoringService - see ScoringServiceTest.
 *
 * The test is skipped when Node is not installed.
 */
class FrontendScoringTest extends TestCase
{
    private function nodeBinary(): ?string
    {
        foreach (['node', 'node.exe'] as $binary) {
            $path = @exec('where '.$binary.' 2>nul', $output, $code);
            if ($code === 0 && ! empty($output)) {
                return $output[0];
            }
        }

        return null;
    }

    private function runNodeScript(string $script): array
    {
        $node = $this->nodeBinary();

        if (! $node) {
            $this->markTestSkipped('Node.js is not installed - the frontend scoring test cannot run.');
        }

        $path = base_path('tests/Support/'.$script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            escapeshellarg($node).' '.escapeshellarg($path),
            $descriptors,
            $pipes
        );

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, 'The frontend test script failed: '.$stdout.$stderr);

        $decoded = json_decode($stdout, true);

        $this->assertIsArray($decoded, 'The frontend test script must return JSON. Output: '.$stdout);
        $this->assertTrue($decoded['ok'] ?? false, 'The frontend test script reported a failure.');

        return $decoded['results'];
    }

    public function test_the_submission_confirmation_uses_a_custom_modal(): void
    {
        $results = $this->runNodeScript('evaluate-submit-dom.test.mjs');

        // Clicking the submit button opens the modal and sends nothing.
        $this->assertTrue($results['afterSubmitClick']['dialogVisible']);
        $this->assertTrue($results['afterSubmitClick']['prevented']);
        $this->assertSame(0, $results['afterSubmitClick']['submissions']);

        // Cancelling closes the modal without submitting.
        $this->assertTrue($results['afterCancel']['dialogHidden']);
        $this->assertSame(0, $results['afterCancel']['submissions']);
        $this->assertSame('Submit Evaluation', $results['afterCancel']['label']);

        // Confirming sends the form exactly once and shows the loading state.
        $this->assertSame(1, $results['afterConfirm']['submissions']);
        $this->assertTrue($results['afterConfirm']['confirmDisabled']);
        $this->assertStringContainsString('Submitting', $results['afterConfirm']['confirmLabel']);
        $this->assertTrue($results['afterConfirm']['cancelDisabled']);
        $this->assertTrue($results['afterConfirm']['submitButtonDisabled']);
        $this->assertSame('Submitting...', $results['afterConfirm']['label']);

        // A repeated click cannot send a second request.
        $this->assertSame(1, $results['afterRepeatedClick']['submissions']);
    }

    public function test_the_correction_and_reset_workflow_is_exclusive_and_confirmed(): void
    {
        $results = $this->runNodeScript('evaluate-correction-dom.test.mjs');

        // The default state is read-only and only offers the two entry points.
        $this->assertTrue($results['defaultState']['radiosDisabled']);
        $this->assertTrue($results['defaultState']['correctionFormHidden']);
        $this->assertTrue($results['defaultState']['actionsVisible']);
        $this->assertTrue($results['defaultState']['editDialogHidden']);
        $this->assertTrue($results['defaultState']['saveDialogHidden']);
        $this->assertTrue($results['defaultState']['resetDialogHidden']);

        // "Edit My Scores" asks for a confirmation first.
        $this->assertTrue($results['afterEditClick']['editDialogVisible']);
        $this->assertTrue($results['afterEditClick']['radiosDisabled']);

        // Cancelling the dialog changes nothing.
        $this->assertTrue($results['afterEditCancel']['editDialogHidden']);
        $this->assertTrue($results['afterEditCancel']['radiosDisabled']);
        $this->assertTrue($results['afterEditCancel']['actionsVisible']);

        // The correction mode enables the scores and hides the read-only
        // actions, so correction and reset are never active at the same time.
        $this->assertTrue($results['correctionMode']['radiosEnabled']);
        $this->assertTrue($results['correctionMode']['correctionFormVisible']);
        $this->assertTrue($results['correctionMode']['actionsHidden']);
        $this->assertStringContainsString('Correction mode', $results['correctionMode']['status']);

        // "Save Corrections" asks for a confirmation and sends nothing before.
        $this->assertTrue($results['afterSaveClick']['saveDialogVisible']);
        $this->assertFalse($results['afterSaveClick']['requestSent']);
        $this->assertTrue($results['afterSaveCancel']['requestSent'] === false);
        $this->assertTrue($results['afterSaveCancel']['radiosEnabled']);

        // The confirmed correction is sent, the page returns to the read-only
        // mode and a success message is shown.
        $this->assertTrue($results['afterConfirmedSave']['requestSent']);
        $this->assertSame(5, $results['afterConfirmedSave']['score']);
        $this->assertTrue($results['afterConfirmedSave']['radiosDisabled']);
        $this->assertTrue($results['afterConfirmedSave']['correctionFormHidden']);
        $this->assertTrue($results['afterConfirmedSave']['actionsVisible']);
        $this->assertStringContainsString('corrections have been saved', $results['afterConfirmedSave']['message']);
        $this->assertStringContainsString('Submitted at', $results['afterConfirmedSave']['status']);

        // A failed save keeps the unsaved edits and shows an error.
        $this->assertTrue($results['afterFailedSave']['radiosEnabled']);
        $this->assertStringContainsString('Save failed', $results['afterFailedSave']['message']);
        $this->assertTrue($results['afterFailedSave']['actionsHidden']);

        // Cancelling the correction restores the persisted values.
        $this->assertTrue($results['afterCancelCorrection']['reloaded']);

        // The reset uses its own confirmation dialog.
        $this->assertTrue($results['afterResetClick']['resetDialogVisible']);
    }

    public function test_the_frontend_calculates_the_summary_immediately(): void
    {
        $results = $this->runNodeScript('frontend-scoring.test.mjs');

        // Clicking two score buttons updates the summary right away.
        $this->assertSame(2, $results['summaryAfterTwoClicks']['scoredCount']);
        $this->assertEqualsWithDelta(16.0, $results['summaryAfterTwoClicks']['total'], 0.001);
        $this->assertTrue($results['summaryAfterTwoClicks']['provisional']);
        $this->assertFalse($results['summaryAfterTwoClicks']['complete']);

        // Unselected criteria show no score and do not contribute.
        $this->assertNull($results['summaryAfterTwoClicks']['selectedScores'][1]);
        $this->assertEqualsWithDelta(0.0, $results['summaryAfterTwoClicks']['contributions'][1], 0.001);

        // A complete evaluation reaches the maximum of 100.
        $this->assertEqualsWithDelta(100.0, $results['summaryAllTen']['total'], 0.001);
        $this->assertSame(6, $results['summaryAllTen']['scoredCount']);
        $this->assertTrue($results['summaryAllTen']['complete']);
        $this->assertSame([10.0, 10.0, 20.0, 20.0, 15.0, 25.0], array_map('floatval', $results['summaryAllTen']['contributions']));
    }

    public function test_the_frontend_rejects_invalid_scores(): void
    {
        $results = $this->runNodeScript('frontend-scoring.test.mjs');

        $invalid = collect($results['invalidScores'])->keyBy('value');

        $this->assertFalse($invalid[0]['valid']);
        $this->assertFalse($invalid[6]['valid']);
        $this->assertFalse($invalid[10]['valid']);
        $this->assertFalse($invalid[11]['valid']);
        $this->assertFalse($invalid[13]['valid']);
    }

    public function test_the_page_wiring_updates_the_summary_and_gates_the_submit_button(): void
    {
        $results = $this->runNodeScript('evaluate-dom.test.mjs');

        // Immediate recalculation after a click.
        $this->assertSame('2', $results['afterTwoClicks']['scoredCount']);
        $this->assertSame('28.00', $results['afterTwoClicks']['total']);
        $this->assertSame('4', $results['afterTwoClicks']['crit1Score']);
        $this->assertSame('8.00', $results['afterTwoClicks']['crit1Contribution']);
        $this->assertSame('-', $results['afterTwoClicks']['crit2Score']);
        $this->assertStringContainsString('Provisional', $results['afterTwoClicks']['label']);
        $this->assertTrue($results['afterTwoClicks']['submitDisabled']);

        // The payload carries the real selections.
        $this->assertSame(4, $results['payloadAfterClicks']['scores']['1']);
        $this->assertSame(5, $results['payloadAfterClicks']['scores']['3']);
        $this->assertNull($results['payloadAfterClicks']['scores']['2']);

        // A failed save keeps the selections.
        $this->assertStringStartsWith('Save failed', $results['afterNetworkFailure']['status']);
        $this->assertSame('3', $results['afterNetworkFailure']['crit5Score']);
        $this->assertSame('3', $results['afterNetworkFailure']['scoredCount']);
        $this->assertTrue($results['afterNetworkFailure']['submitDisabled']);

        // A server error is reported with the server message.
        $this->assertStringContainsString('judging period', $results['afterHttpError']['status']);

        // After a successful save the submit button is enabled and the
        // selections are preserved.
        $this->assertFalse($results['afterCompleteSave']['submitDisabled']);
        $this->assertSame('6', $results['afterCompleteSave']['scoredCount']);
        $this->assertSame('81.00', $results['afterCompleteSave']['total']);
        $this->assertSame('4', $results['afterCompleteSave']['crit2Score']);
    }
}
