<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * PeriodSteps — Wave A step A16.
 *
 * A16: period/fiscal year close on the web surface (no API route).
 */
trait PeriodSteps
{
    /**
     * A16 — web: fiscal year index.
     */
    protected function itManagesPeriods(): void
    {
        $resp = $this->webClient->get('/accounting/fiscal-years');
        $this->assertContains($resp['status'], [200, 302], 'A16 web fiscal years');
    }
}
