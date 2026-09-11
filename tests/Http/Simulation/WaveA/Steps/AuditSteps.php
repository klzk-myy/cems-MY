<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * AuditSteps — Wave A step A31.
 *
 * A31: audit logs on the web surface (no API v1 route).
 */
trait AuditSteps
{
    /**
     * A31 — web: audit trail index.
     */
    protected function itViewsAuditLogs(): void
    {
        $resp = $this->webClient->get('/admin/audit-logs');
        $this->assertContains($resp['status'], [200, 302], 'A31 web audit logs');
    }
}
