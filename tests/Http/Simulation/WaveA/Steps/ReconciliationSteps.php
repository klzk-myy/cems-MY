<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * ReconciliationSteps — Wave A step A14.
 *
 * A14: bank reconciliation on the web surface (no API route).
 */
trait ReconciliationSteps
{
    /**
     * A14 — web: bank reconciliation index.
     */
    protected function itManagesBankReconciliation(): void
    {
        $resp = $this->webClient->get('/accounting/reconciliation');
        $this->assertSurfaceStatus($resp, 200, 'A14 web bank reconciliation');
    }
}
