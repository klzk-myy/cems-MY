<?php

namespace Tests\Http\Simulation\WaveB\Steps;

/**
 * EdgeSteps — Wave B business-rule edge scenarios.
 *
 * Each step submits an invalid or duplicated operation through a real route
 * and asserts both the HTTP rejection and the absence of derived state.
 */
trait EdgeSteps
{
    /**
     * B5 — replaying the same idempotency key must not create a second
     * transaction.
     */
    protected function itDeduplicatesIdempotentBooking(): void
    {
        $this->webClient->login('sim_teller', 'Test@1234');
        $this->openCounterOverWeb();

        $payload = $this->bookingPayload('wave-b-idem-');
        $resp = $this->webClient->post('/transactions', $payload);
        $this->assertSurfaceStatus($resp, 302, 'B5 first booking');

        $resp = $this->webClient->post('/transactions', $payload);
        $this->assertContains($resp['status'], [302, 409, 422], 'B5 replay must not succeed silently');

        $count = $this->state->oracle->scalar(
            'SELECT COUNT(*) FROM transactions WHERE idempotency_key = ?',
            [$payload['idempotency_key']]
        );
        $this->assertSame(1, (int) $count, 'B5: idempotent replay created a duplicate transaction');
    }

    /**
     * B6 — invalid payloads are rejected and write nothing (web surface).
     */
    protected function itRejectsInvalidBookingPayloads(): void
    {
        if (! $this->surfaceAllows('web')) {
            return;
        }

        $this->webClient->login('sim_teller', 'Test@1234');
        $this->openCounterOverWeb();

        foreach ([
            'zero amount' => ['amount_foreign' => '0'],
            'negative amount' => ['amount_foreign' => '-100.00'],
            'unknown currency' => ['currency_code' => 'XXX'],
            'missing customer' => ['customer_id' => 999999],
        ] as $label => $overrides) {
            $payload = $this->bookingPayload('wave-b-invalid-');
            $payload = array_merge($payload, $overrides);

            $resp = $this->webClient->post('/transactions', $payload);
            $this->assertNotSame(500, $resp['status'], "B6 {$label}: must not 500");

            $count = $this->state->oracle->scalar(
                'SELECT COUNT(*) FROM transactions WHERE idempotency_key = ?',
                [$payload['idempotency_key']]
            );
            $this->assertSame(0, (int) $count, "B6 {$label}: invalid payload wrote a transaction row");
        }
    }

    /**
     * B7 — a teller-entered rate outside the configured deviation band is
     * blocked, while a rate inside the band books normally.
     */
    protected function itBlocksDeviatingRates(): void
    {
        if (! $this->surfaceAllows('web')) {
            return;
        }

        $this->webClient->login('sim_teller', 'Test@1234');
        $this->openCounterOverWeb();

        // Seeded USD rate is 4.5000 buy; 9.00 is a 100% deviation and must
        // be rejected by the rate tolerance guard.
        $payload = $this->bookingPayload('wave-b-rate-');
        $payload['rate'] = '9.00';
        $resp = $this->webClient->post('/transactions', $payload);
        $this->assertNotSame(500, $resp['status'], 'B7 deviating rate: must not 500');

        $count = $this->state->oracle->scalar(
            'SELECT COUNT(*) FROM transactions WHERE idempotency_key = ?',
            [$payload['idempotency_key']]
        );
        $this->assertSame(0, (int) $count, 'B7: deviating rate wrote a transaction row');

        // Boundary: the configured band is thresholds.rates
        // .max_deviation_percent = 0.05, which is a FRACTION (5%, not 0.05%),
        // so 4.5010 against the 4.5000 market rate deviates ~0.022% and must
        // book — proving the guard rejects on deviation, not on any rate
        // variation. The tighter teller limit (±0.5%) is also satisfied.
        $txId = $this->bookOverWeb('wave-b-rate-', ['rate' => '4.5010']);
        $this->assertGreaterThan(0, $txId, 'B7: in-band rate should book normally');
    }

    /**
     * B8 — booking against a counter with no open session is rejected.
     */
    protected function itRejectsBookingOnClosedCounter(): void
    {
        if (! $this->surfaceAllows('web')) {
            return;
        }

        $this->webClient->login('sim_teller', 'Test@1234');

        $payload = $this->bookingPayload('wave-b-closed-');
        $resp = $this->webClient->post('/transactions', $payload);
        $this->assertNotSame(500, $resp['status'], 'B8 closed counter: must not 500');

        $count = $this->state->oracle->scalar(
            'SELECT COUNT(*) FROM transactions WHERE idempotency_key = ?',
            [$payload['idempotency_key']]
        );
        $this->assertSame(0, (int) $count, 'B8: booking on a closed counter wrote a transaction row');
    }

    /**
     * B10 — approving a cancellation that was never requested is rejected
     * and leaves the transaction untouched.
     */
    protected function itRejectsUnrequestedCancellationApproval(): void
    {
        $this->webClient->login('sim_teller', 'Test@1234');
        $this->openCounterOverWeb();
        // Large amount so the booking lands in PendingApproval (small amounts
        // auto-complete and never exercise the approval state).
        $txId = $this->bookOverWeb('wave-b-no-cancel-', ['amount_foreign' => '3000.00']);

        // As in B9, web rejection is a redirect + flash error; the oracle
        // assertion is the verdict: nothing may change without a pending
        // cancellation request.
        $this->asWebUser('sim_manager', function () use ($txId): void {
            $this->webClient->post('/transactions/'.$txId.'/approve-cancellation', [
                'reason' => 'Approving a cancellation that was never requested.',
            ]);
        });

        $status = $this->state->oracle->scalar('SELECT status FROM transactions WHERE id = ?', [$txId]);
        $this->assertSame('PendingApproval', $status, 'B10: transaction must be unchanged');
    }
}
