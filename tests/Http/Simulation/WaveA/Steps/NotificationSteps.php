<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * NotificationSteps — Wave A step A27.
 *
 * A27: notifications on the web surface (no API v1 route).
 */
trait NotificationSteps
{
    /**
     * A27 — web: notifications index.
     */
    protected function itManagesNotifications(): void
    {
        $resp = $this->webClient->get('/notifications/preferences');
        $this->assertContains($resp['status'], [200, 302], 'A27 web notifications');
    }
}
