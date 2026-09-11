<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * AlertSteps — Wave A step A29.
 *
 * A29: system alerts on the web surface (no API v1 route).
 */
trait AlertSteps
{
    /**
     * A29 — web: alerts index.
     */
    protected function itManagesSystemAlerts(): void
    {
        $resp = $this->webClient->get('/system/alerts');
        $this->assertSurfaceStatus($resp, 200, 'A29 web alerts');
    }
}
