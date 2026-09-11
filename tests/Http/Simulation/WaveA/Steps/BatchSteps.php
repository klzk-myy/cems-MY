<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * BatchSteps — Wave A step A25.
 *
 * A25: batch transaction import on the web surface (no API v1 route).
 */
trait BatchSteps
{
    /**
     * A25 — web: batch upload page.
     */
    protected function itManagesBatchImport(): void
    {
        $resp = $this->webClient->get('/transactions/batch-upload');
        $this->assertSurfaceStatus($resp, 200, 'A25 web batch upload');
    }
}
