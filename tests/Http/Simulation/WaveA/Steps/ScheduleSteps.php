<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * ScheduleSteps — Wave A step A28.
 *
 * A28: report schedules on the web surface (no API v1 route).
 */
trait ScheduleSteps
{
    /**
     * A28 — web: report schedules index.
     */
    protected function itManagesReportSchedules(): void
    {
        $resp = $this->webClient->get('/reports/schedules');
        $this->assertContains($resp['status'], [200, 302], 'A28 web report schedules');
    }
}
