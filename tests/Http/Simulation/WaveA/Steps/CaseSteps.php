<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * CaseSteps — Wave A step A20.
 *
 * A20: compliance case management on the API surface.
 */
trait CaseSteps
{
    /**
     * A20 — API: case index.
     */
    protected function itManagesCases(): void
    {
        $api = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $api->get('/compliance/cases');
        $this->assertSurfaceStatus($resp, 200, 'A20 API cases');
    }
}
