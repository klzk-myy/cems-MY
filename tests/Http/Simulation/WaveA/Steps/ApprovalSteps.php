<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * ApprovalSteps — Wave A step A5.
 *
 * A5: approve a pending transaction on both surfaces and assert the state
 * machine transitioned.
 */
trait ApprovalSteps
{
    /**
     * A5 — compliance officer approves the pending transaction on the web surface.
     */
    protected function itApprovesTransaction(int $txId): void
    {
        // A6 (request cancellation) also runs as the branch manager, so we
        // restore the manager identity rather than the default teller.
        $this->asWebUser('sim_compliance', function () use ($txId): void {
            $resp = $this->webRequester('POST', '/transactions/'.$txId.'/approve', []);

            $this->assertSurfaceStatus($resp, 302, 'A5 web approve');
        }, 'sim_compliance');

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);

        $this->assertNotSame('pending_approval', $status, 'A5: transaction did not transition');
    }

    /**
     * A5b — compliance officer approves the pending transaction on the API surface.
     */
    protected function itApprovesTransactionViaApi(int $txId): void
    {
        $api = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $api->post('/transactions/'.$txId.'/approve', []);

        $this->assertSurfaceStatus($resp, 200, 'A5b API approve');

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);

        $this->assertNotSame('pending_approval', $status, 'A5b: transaction did not transition');
    }
}
