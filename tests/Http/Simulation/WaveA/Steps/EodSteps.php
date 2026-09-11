<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * EodSteps — Wave A step A8.
 *
 * A8: end-of-day reconciliation on the web surface, asserting zero variance.
 */
trait EodSteps
{
    /**
     * A8 — web: EOD page.
     */
    protected function itRunsEodReconciliation(): void
    {
        $resp = $this->webClient->get('/eod');
        $this->assertSurfaceStatus($resp, 200, 'A8 web EOD');
    }

    /**
     * A8b — API: reconciliation report for today.
     */
    protected function itRunsEodReconciliationViaApi(): void
    {
        $api = $this->newApiClient($this->tokenFor('manager'));
        $resp = $api->get('/eod/reconciliation/'.now()->toDateString());
        $this->assertSurfaceStatus($resp, 200, 'A8b API reconciliation');
    }
}
