<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * CancellationSteps — Wave A step A6.
 *
 * A6: request a cancellation, then approve it, on both surfaces, and assert
 * the reversal journal is balanced.
 */
trait CancellationSteps
{
    /**
     * A6 — request cancellation on the web surface.
     */
    protected function itRequestsCancellation(int $txId): void
    {
        // Runs as the branch manager explicitly — in the full sweep the prior
        // approval step (A5) already left the manager logged in, but a
        // surface-gated run (--surface=api) may have skipped A5 entirely. A6c
        // (approve cancellation) requires the requester to be the manager.
        $resp = null;
        $this->asWebUser('sim_manager', function () use (&$resp, $txId): void {
            $resp = $this->webClient->post('/transactions/'.$txId.'/cancel', [
                'cancellation_reason' => 'Customer requested a refund for this purchase.',
                'confirm_understanding' => '1',
            ]);
        }, 'sim_manager');

        $this->assertSurfaceStatus($resp, 302, 'A6 web cancel request');

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);

        $this->assertSame('PendingCancellation', $status, 'A6: transaction should be pending cancellation');
    }

    /**
     * A6b — request cancellation on the API surface.
     */
    protected function itRequestsCancellationViaApi(int $txId): void
    {
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/transactions/'.$txId.'/request-cancellation', [
            'reason' => 'Customer requested a refund for this purchase.',
        ]);

        $this->assertSurfaceStatus($resp, 200, 'A6b API cancel request');

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);

        $this->assertSame('PendingCancellation', $status, 'A6b: transaction should be pending cancellation');
    }

    /**
     * A6c — compliance approves the cancellation on the web surface.
     *
     * Approving the cancellation of a completed transaction is a reversal,
     * which is compliance-only. The manager who requested it is also blocked
     * by segregation of duties.
     */
    protected function itApprovesCancellation(int $txId): void
    {
        $this->asWebUser('sim_compliance', function () use ($txId): void {
            $resp = $this->webClient->post('/transactions/'.$txId.'/approve-cancellation', [
                'reason' => 'Cancellation approved by compliance.',
            ]);

            $this->assertSurfaceStatus($resp, 302, 'A6c web approve-cancellation');

            $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);

            $this->assertSame('Cancelled', $status, 'A6c: transaction should be cancelled');
            $this->assertReversalBalanced($txId, 'A6c web');
        }, 'sim_manager');
    }

    /**
     * A6d — compliance approves the cancellation on the API surface.
     */
    protected function itApprovesCancellationViaApi(int $txId): void
    {
        $api = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $api->post('/transactions/'.$txId.'/approve-cancellation', [
            'reason' => 'Cancellation approved by compliance.',
        ]);

        $this->assertSurfaceStatus($resp, 200, 'A6d API approve-cancellation');

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);

        $this->assertSame('Cancelled', $status, 'A6d: transaction should be cancelled');
        $this->assertReversalBalanced($txId, 'A6d API');
    }

    /**
     * Assert the reversal journal (linked via original_transaction_id) is
     * double-entry balanced.
     */
    private function assertReversalBalanced(int $txId, string $label): void
    {
        $rows = $this->state->oracle->query(
            "SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit
             FROM journal_lines
             JOIN journal_entries ON journal_lines.journal_entry_id = journal_entries.id
             WHERE journal_entries.reference_type = 'Transaction'
               AND (journal_entries.reference_id = ?
                OR journal_entries.reference_id IN (
                    SELECT id FROM transactions WHERE original_transaction_id = ?
                ))",
            [$txId, $txId]
        );

        $debit = (float) ($rows[0]['total_debit'] ?? 0);
        $credit = (float) ($rows[0]['total_credit'] ?? 0);

        $this->assertSame(0.0, round($debit - $credit, 2), "{$label}: reversal journal debits != credits");
    }
}
