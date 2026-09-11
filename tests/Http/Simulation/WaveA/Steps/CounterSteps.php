<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * CounterSteps — Wave A step A2.
 *
 * A2: open a counter on both surfaces. Web POSTs /counters/{c}/open with
 * opening_floats; API POSTs /api/v1/counters/{c}/opening-request then
 * /api/v1/counters/{c}/approve-and-open (manager).
 */
trait CounterSteps
{
    /**
     * A2 — open the seeded HQ counter on the web surface.
     */
    protected function itOpensCounter(): void
    {
        $code = $this->counterCode();

        $resp = $this->webClient->post('/counters/'.$code.'/open', [
            'opening_floats' => [
                ['currency_id' => 'USD', 'amount' => 100000],
                ['currency_id' => 'EUR', 'amount' => 100000],
                ['currency_id' => 'GBP', 'amount' => 100000],
                ['currency_id' => 'MYR', 'amount' => 100000],
            ],
            'notes' => 'Wave A open',
        ]);
        $this->assertSurfaceStatus($resp, 302, 'A2 web counter open');

        $this->assertCounterSessionOpen($this->state->counterId, 'A2 web');
    }

    /**
     * A2b — API: teller initiates, manager approves and opens.
     *
     * The API counter routes bind on the integer counter id, unlike the web
     * routes which bind on the Counter model's `code` route key.
     */
    protected function itOpensCounterViaApi(): void
    {
        // Close any existing counter session from the web step (A2) so the
        // API step starts from a clean state. Both surfaces share one DB.
        $this->itClosesCounter();

        // No pool funding step: SimulationSeeder seeds each HQ pool with
        // 500,000 available, which covers the opening request. Funding via
        // actingAs() leaks the manager identity past Sanctum Bearer auth for
        // subsequent in-process API calls, so it was removed.
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/counters/'.$this->state->counterId.'/opening-request', [
            'requested_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
        ]);
        $this->assertSurfaceStatus($resp, 200, 'A2b API opening-request');

        $manager = $this->newApiClient($this->tokenFor('manager'));
        $resp = $manager->post('/counters/'.$this->state->counterId.'/approve-and-open', [
            'teller_id' => $this->state->tellerId,
            'approved_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
            'daily_limits' => [
                'USD' => 500000, 'EUR' => 500000, 'GBP' => 500000, 'MYR' => 500000,
            ],
        ]);
        $this->assertSurfaceStatus($resp, 200, 'A2b API approve-and-open');

        $this->assertCounterSessionOpen($this->state->counterId, 'A2b API');
    }

    /**
     * Close A2's open session via the real close route so A2b can open the
     * counter afresh on the API surface. Both surfaces share one DB, so
     * A2's session would otherwise block approve-and-open with "Counter is
     * already open today". The close mirrors A2's opening floats, so the
     * variance is zero and no supervisor approval is needed.
     */
    private function itClosesCounter(): void
    {
        $this->asWebUser('sim_manager', function (): void {
            $resp = $this->webClient->post('/counters/'.$this->counterCode().'/close', [
                'closing_floats' => [
                    ['currency_id' => 'USD', 'amount' => 100000],
                    ['currency_id' => 'EUR', 'amount' => 100000],
                    ['currency_id' => 'GBP', 'amount' => 100000],
                    ['currency_id' => 'MYR', 'amount' => 100000],
                ],
            ]);

            $this->assertSurfaceStatus($resp, 302, 'A2b precondition: web counter close');

            $open = $this->state->oracle->scalar(
                "SELECT COUNT(*) FROM counter_sessions WHERE status = 'open'"
            );

            $this->assertSame(0, (int) $open, 'A2b precondition: no counter session should remain open');
        });
    }

    /**
     * Assert the counter has an open session for today via the oracle.
     */
    private function assertCounterSessionOpen(int $counterId, string $label): void
    {
        $open = $this->state->oracle->scalar(
            "SELECT COUNT(*) FROM counter_sessions
             WHERE counter_id = ? AND DATE(session_date) = DATE(?) AND status = 'open'",
            [$counterId, now()]
        );

        $this->assertSame(1, (int) $open, "{$label}: counter should have one open session");
    }
}
