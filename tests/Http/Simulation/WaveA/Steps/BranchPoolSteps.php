<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * BranchPoolSteps — Wave A step A30.
 *
 * A30: branch pool fund/debit on the web surface (no API v1 route).
 */
trait BranchPoolSteps
{
    /**
     * A30 — web: branch pool index.
     */
    protected function itManagesBranchPools(): void
    {
        $resp = $this->webClient->get('/branch-pools');
        $this->assertSurfaceStatus($resp, 200, 'A30 web branch pools');
    }
}
