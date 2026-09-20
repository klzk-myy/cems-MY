<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * CounterLifecycleSteps — Wave A step A13.
 *
 * A13 emergency close via the API emergency-close route. The web handover
 * (A12) and web emergency-close routes were retired with the drawerless UI;
 * handover acknowledge coverage lives in
 * tests/Feature/CounterHandoverAcknowledgeTest.
 */
trait CounterLifecycleSteps
{
    /**
     * A13 — emergency close via the API surface.
     *
     * The service rejects sessions younger than 30 minutes
     * (EmergencyCloseSessionTooNewException), so the test clock travels
     * forward before closing and back afterwards.
     */
    protected function itEmergencyClosesCounter(): void
    {
        $this->travel(31)->minutes();

        try {
            $api = $this->newApiClient($this->tokenFor('teller'));
            $resp = $api->post('/counters/'.$this->state->counterId.'/emergency-close', [
                'reason' => 'Wave A emergency closure.',
            ]);
            $this->assertContains($resp['status'], [200, 201, 202], 'A13 API emergency close');
        } finally {
            $this->travelBack();
        }
    }
}
