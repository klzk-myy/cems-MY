<?php

namespace Tests\Http\Simulation\WaveA\Steps;

use Illuminate\Support\Facades\DB;

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
     * (EmergencyCloseSessionTooNewException). The clock is NOT travelled:
     * +31 minutes near midnight rolls session_date into the next day and the
     * whereDate lookup then reports "no active session" — a flake, not a
     * real outcome. Backdating opened_at satisfies the age guard instead.
     */
    protected function itEmergencyClosesCounter(): void
    {
        DB::table('counter_sessions')
            ->where('counter_id', $this->state->counterId)
            ->where('status', 'open')
            ->update(['opened_at' => now()->subMinutes(45)]);

        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/counters/'.$this->state->counterId.'/emergency-close', [
            'reason' => 'Wave A emergency closure.',
        ]);
        $this->assertContains($resp['status'], [200, 201, 202], 'A13 API emergency close');
    }
}
