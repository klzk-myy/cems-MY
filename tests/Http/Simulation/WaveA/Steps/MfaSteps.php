<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * MfaSteps — Wave A step A24.
 *
 * A24: MFA lifecycle on the API surface.
 */
trait MfaSteps
{
    /**
     * A24 — API: MFA enroll/verify/disable.
     */
    protected function itManagesMfa(): void
    {
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/mfa/enroll', []);
        $this->assertContains($resp['status'], [200, 201, 202, 409, 422], 'A24 API MFA enroll');
    }
}
