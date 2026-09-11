<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * EddSteps — Wave A step A19.
 *
 * A19: enhanced due diligence on the API surface.
 */
trait EddSteps
{
    /**
     * A19 — API: EDD index.
     */
    protected function itManagesEdd(): void
    {
        $api = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $api->get('/compliance/edd');
        $this->assertSurfaceStatus($resp, 200, 'A19 API EDD');
    }
}
