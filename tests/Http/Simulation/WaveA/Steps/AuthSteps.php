<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * AuthSteps — Wave A step A1.
 *
 * A1: authenticate on both surfaces. Web logs in via the login form
 * (session + CSRF); API calls GET /api/v1/user with a Sanctum token.
 */
trait AuthSteps
{
    /**
     * A1 — authenticate the teller on both surfaces.
     */
    protected function itAuthenticates(): void
    {
        // The web login also runs under --surface=api because later steps use
        // the web session for plumbing (role switches, API preconditions).
        $this->webClient->login('sim_teller', 'Test@1234');

        if (! $this->surfaceAllows('api')) {
            return;
        }

        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->get('/user');

        $this->assertSurfaceStatus($resp, 200, 'A1 API auth');
        $data = $this->apiData($resp);
        $this->assertSame('sim_teller', $data['username'] ?? null, 'A1 API auth returns the teller');
    }

    /**
     * A1b — the web login round-trips the session forward to the dashboard.
     */
    protected function itHasAuthenticatedWebSession(): void
    {
        $resp = $this->webClient->get('/dashboard');

        $this->assertSurfaceStatus($resp, 200, 'A1b web session');
    }
}
