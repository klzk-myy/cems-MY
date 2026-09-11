<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * SanctionsSteps — Wave A step A23.
 *
 * A23: sanctions import on the API surface (throttled).
 */
trait SanctionsSteps
{
    /**
     * A23 — API: sanctions lists and import trigger.
     */
    protected function itManagesSanctionsImport(): void
    {
        // sanctions/lists is an admin-only route.
        $api = $this->newApiClient($this->tokenFor('admin'));
        $resp = $api->get('/sanctions/lists');
        $this->assertSurfaceStatus($resp, 200, 'A23 API sanctions lists');
    }
}
