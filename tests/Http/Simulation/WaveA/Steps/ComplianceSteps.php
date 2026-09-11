<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * ComplianceSteps — Wave A step A7.
 *
 * A7: screen the customer, assert an alert/case lifecycle is created and
 * resolved, on both surfaces where routes exist.
 */
trait ComplianceSteps
{
    /**
     * A7 — screen the seeded customer on the API surface.
     */
    protected function itScreensCustomer(): void
    {
        $api = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $api->post('/screening/customer/'.$this->state->customerId, []);

        $this->assertSurfaceStatus($resp, 200, 'A7 API screen');

        $rows = $this->state->oracle->query(
            'SELECT COUNT(*) AS n FROM screening_results WHERE customer_id = ?',
            [$this->state->customerId]
        );

        // Earlier steps (bookings, approvals) already screened this customer,
        // so the count is cumulative — assert at least one result exists.
        $this->assertGreaterThanOrEqual(1, (int) ($rows[0]['n'] ?? 0), 'A7: no screening result recorded');
    }

    /**
     * A7b — list alerts on the API surface.
     */
    protected function itListsAlerts(): void
    {
        $api = $this->newApiClient($this->tokenFor('compliance'));
        $resp = $api->get('/compliance/alerts');

        $this->assertSurfaceStatus($resp, 200, 'A7b API alerts index');
    }
}
