<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * AllocationSteps — Wave A step A3.
 *
 * A3: exercise the teller allocation domain on both surfaces. Allocation
 * *creation* has no HTTP route (it is a service method), so the step drives
 * the read paths that exist on each surface and asserts the seeded
 * allocation is present and active via the oracle.
 */
trait AllocationSteps
{
    /**
     * A3 — manager views the allocation list on the web surface.
     */
    protected function itViewsAllocations(): void
    {
        // The web allocation list is manager/admin-only; the scoped helper
        // restores the teller session afterwards.
        $this->asWebUser('sim_manager', function (): void {
            $resp = $this->webClient->get('/allocations');

            $this->assertSurfaceStatus($resp, 200, 'A3 web allocations index');
        });
    }

    /**
     * A3b — API: teller reads their own active allocation.
     */
    protected function itViewsAllocationsViaApi(): void
    {
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->get('/allocations/my-active?currency_code=USD');

        $this->assertSurfaceStatus($resp, 200, 'A3b API my-active allocation');
        $body = json_decode($resp['body'], true);
        $this->assertIsArray($body, 'A3b API my-active allocation body');

        $active = $this->state->oracle->scalar(
            "SELECT COUNT(*) FROM teller_allocations
             WHERE user_id = ? AND status = 'active' AND DATE(session_date) = DATE(?)",
            [$this->state->tellerId, now()]
        );

        $this->assertGreaterThanOrEqual(1, (int) $active, 'A3b: teller has at least one active allocation');
    }

    /**
     * A3c — API: manager reads the active allocation list for the branch.
     */
    protected function itViewsBranchAllocationsViaApi(): void
    {
        $api = $this->newApiClient($this->tokenFor('manager'));
        $resp = $api->get('/allocations/active');

        $this->assertSurfaceStatus($resp, 200, 'A3c API active allocations');
    }
}
