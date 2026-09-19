<?php

namespace Tests\Http\Simulation\WaveB\Steps;

/**
 * AttackSteps — Wave B attack scenarios.
 *
 * Each step drives a hostile request at a real route and asserts the
 * application rejects it. Assertions are black-box: HTTP status plus an
 * oracle check that no state was written.
 */
trait AttackSteps
{
    /**
     * B1 — API requests without valid credentials are rejected.
     */
    protected function itRejectsUnauthenticatedApiRequests(): void
    {
        $noToken = $this->newApiClient('');
        $resp = $noToken->get('/transactions');
        $this->assertSurfaceStatus($resp, 401, 'B1 API without token');

        $bogus = $this->newApiClient('not-a-real-token');
        $resp = $bogus->get('/transactions');
        $this->assertSurfaceStatus($resp, 401, 'B1 API with bogus token');

        $resp = $bogus->post('/transactions', $this->bookingPayload('wave-b-unauth-'));
        $this->assertSurfaceStatus($resp, 401, 'B1 API booking with bogus token');
    }

    /**
     * B2 — web requests without a session are bounced to the login page.
     */
    protected function itRejectsUnauthenticatedWebRequests(): void
    {
        $resp = $this->webClient->get('/dashboard');
        $this->assertSurfaceStatus($resp, 302, 'B2 web dashboard without session');

        $resp = $this->webClient->get('/transactions');
        $this->assertSurfaceStatus($resp, 302, 'B2 web transactions index without session');
    }

    /**
     * B3 — a forged session cookie grants nothing: protected routes must
     * bounce to login and no state may be written.
     *
     * (Laravel's VerifyCsrfToken is bypassed under runningUnitTests(), so an
     * in-process harness cannot probe the CSRF token itself — the session
     * boundary is the attack surface it can honestly test.)
     */
    protected function itRejectsForgedSessionCookie(): void
    {
        $this->webCookies = ['cems-session' => 'forged-session-'.bin2hex(random_bytes(8))];

        $payload = $this->bookingPayload('wave-b-forged-');
        $response = $this->dispatchWeb('POST', '/transactions', $payload, []);

        $this->assertContains(
            $response->status(),
            [302, 401, 403, 419],
            'B3: forged session cookie must not be accepted'
        );
        $this->assertNoBookingRecorded('wave-b-forged-', 'B3');
    }

    /**
     * B4 — a teller cannot approve their own transaction on either surface
     * (approval is a manager action).
     */
    protected function itRejectsTellerSelfApproval(): void
    {
        $this->webClient->login('sim_teller', 'Test@1234');
        $this->openCounterOverWeb();
        // A large amount stays PendingApproval; small amounts auto-complete
        // below the approval threshold and never exercise the approval route.
        $txId = $this->bookOverWeb('wave-b-self-approve-', ['quantity' => '3000.00']);

        if ($this->surfaceAllows('web')) {
            $resp = $this->webClient->post('/transactions/'.$txId.'/approve', []);
            $this->assertSame(403, $resp['status'], 'B4 web: teller approve must be 403');
        }

        if ($this->surfaceAllows('api')) {
            $api = $this->newApiClient($this->tokenFor('teller'));
            $resp = $api->post('/transactions/'.$txId.'/approve', []);
            $this->assertSame(403, $resp['status'], 'B4 API: teller approve must be 403');
        }

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);
        $this->assertSame('pending_approval', $status, 'B4: transaction must remain pending approval');
    }

    /**
     * B9 — segregation of duties: the manager who requested a cancellation
     * cannot approve it themselves; the transaction must stay pending.
     */
    protected function itRejectsSameManagerCancellationApproval(): void
    {
        $this->webClient->login('sim_teller', 'Test@1234');
        $this->openCounterOverWeb();
        $txId = $this->bookOverWeb('wave-b-sod-');

        $resp = null;
        $this->asWebUser('sim_manager', function () use (&$resp, $txId): void {
            $resp = $this->webClient->post('/transactions/'.$txId.'/cancel', [
                'cancellation_reason' => 'Segregation-of-duties probe.',
                'confirm_understanding' => '1',
            ]);
        });
        $this->assertSurfaceStatus($resp, 302, 'B9 web cancel request');

        // Web controllers signal rejection via redirect + flash error, so a
        // 302 here is not success — the oracle assertion below is the verdict:
        // SoD enforced means the transaction is still PendingCancellation.
        $this->asWebUser('sim_manager', function () use ($txId): void {
            $this->webClient->post('/transactions/'.$txId.'/approve-cancellation', [
                'reason' => 'Self-approval attempt.',
            ]);
        });

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);
        $this->assertSame('pending_cancellation', $status, 'B9: same-manager approval must not cancel the transaction');
    }

    /**
     * Shared booking payload for Wave B. Uses a unique idempotency key per
     * scenario so oracle lookups are unambiguous.
     *
     * @return array<string, mixed>
     */
    private function bookingPayload(string $keyPrefix): array
    {
        return [
            'customer_id' => $this->state->customerId,
            'type' => 'Buy',
            'currency_code' => 'USD',
            'quantity' => '100.00',
            'rate' => '4.50',
            'purpose' => 'Wave B probe',
            'source_of_funds' => 'Salary',
            'source_of_wealth' => 'Employer',
            'branch_id' => $this->state->branchId,
            'counter_id' => $this->state->counterId,
            'idempotency_key' => $keyPrefix.uniqid(),
        ];
    }

    /**
     * Book over the web surface and return the new transaction id.
     */
    private function bookOverWeb(string $keyPrefix, array $overrides = []): int
    {
        $payload = array_merge($this->bookingPayload($keyPrefix), $overrides);
        $resp = $this->webClient->post('/transactions', $payload);
        $this->assertSurfaceStatus($resp, 302, 'Wave B booking');

        $txId = $this->state->oracle->scalar(
            'SELECT id FROM transactions WHERE idempotency_key = ?',
            [$payload['idempotency_key']]
        );
        $this->assertNotFalse($txId, 'Wave B booking was not recorded');

        return (int) $txId;
    }

    /**
     * Assert no transaction row carries an idempotency key with this prefix.
     */
    private function assertNoBookingRecorded(string $keyPrefix, string $label): void
    {
        $count = $this->state->oracle->scalar(
            'SELECT COUNT(*) FROM transactions WHERE idempotency_key LIKE ?',
            [$keyPrefix.'%']
        );

        $this->assertSame(0, (int) $count, "{$label}: rejected request wrote a transaction row");
    }

    /**
     * Open the HQ counter on the web surface (booking precondition).
     */
    private function openCounterOverWeb(): void
    {
        $resp = $this->webClient->post('/counters/'.$this->counterCode().'/open', [
            'opening_floats' => [
                ['currency_id' => 'USD', 'quantity' => 100000],
                ['currency_id' => 'EUR', 'quantity' => 100000],
                ['currency_id' => 'GBP', 'quantity' => 100000],
                ['currency_id' => 'MYR', 'quantity' => 100000],
            ],
            'notes' => 'Wave B open',
        ]);
        $this->assertSurfaceStatus($resp, 302, 'Wave B counter open');
    }
}
