<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * PepSteps — Wave A step A21.
 *
 * A21: PEP approval on the web surface (no API v1 route).
 */
trait PepSteps
{
    /**
     * A21 — web: PEP requests index.
     */
    protected function itManagesPepRequests(): void
    {
        $resp = $this->webClient->get('/compliance/pep-approvals');
        $this->assertSurfaceStatus($resp, 200, 'A21 web PEP approvals');
    }
}
