<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * CounterLifecycleSteps — Wave A steps A12, A13.
 *
 * A12 counter handover (web + API acknowledge)
 * A13 emergency close (web + API)
 */
trait CounterLifecycleSteps
{
    /**
     * A12 — web: counter handover.
     */
    protected function itHandsOverCounter(): void
    {
        $code = $this->counterCode();
        $resp = $this->webClient->post('/counters/'.$code.'/handover', [
            'from_user_id' => $this->state->tellerId,
            'to_user_id' => $this->state->tellerId,
            'supervisor_id' => $this->state->managerId,
            'physical_counts' => [],
            'notes' => 'Wave A handover',
        ]);
        $this->assertContains($resp['status'], [302, 200], 'A12 web handover');
    }

    /**
     * A13 — web: emergency close.
     */
    protected function itEmergencyClosesCounter(): void
    {
        $code = $this->counterCode();
        $resp = $this->webClient->post('/counters/'.$code.'/emergency', [
            'reason' => 'Wave A emergency closure.',
        ]);
        $this->assertContains($resp['status'], [302, 200], 'A13 web emergency close');
    }
}
