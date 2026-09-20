<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * CounterSteps — Wave A steps A2, A2b.
 *
 * A2: open the HQ counter via the API opening-request + approve-and-open
 *     flow. The web /counters/{code}/open route was removed in the drawerless
 *     rework — the API flow is the sole entry point and still creates the
 *     till balances downstream steps depend on.
 * A2b: close the session via the API close route, then reopen — covering the
 *     close endpoint and a clean reopen on the same counter.
 */
trait CounterSteps
{
    /**
     * A2 — teller initiates, manager approves and opens.
     *
     * The API counter routes bind on the integer counter id, unlike the
     * removed web routes which bound on the Counter model's `code` route key.
     */
    protected function itOpensCounter(): void
    {
        $this->openCounterViaApi('A2');
    }

    /**
     * A2b — close the A2 session via the real API close route, then reopen,
     * exercising the close endpoint and a clean reopen on the same counter.
     */
    protected function itOpensCounterViaApi(): void
    {
        $this->itClosesCounter();
        $this->openCounterViaApi('A2b');
    }

    /**
     * Shared open flow: teller POSTs opening-request, manager POSTs
     * approve-and-open. Both surfaces share one DB — the resulting session
     * serves web and API booking steps alike.
     */
    private function openCounterViaApi(string $label): void
    {
        // No pool funding step: SimulationSeeder seeds each HQ pool with
        // 500,000 available, which covers the opening request. Funding via
        // actingAs() leaks the manager identity past Sanctum Bearer auth for
        // subsequent in-process API calls, so it was removed.
        $api = $this->newApiClient($this->tokenFor('teller'));
        $resp = $api->post('/counters/'.$this->state->counterId.'/opening-request', [
            'requested_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
        ]);
        $this->assertSurfaceStatus($resp, 200, "{$label} API opening-request");

        $manager = $this->newApiClient($this->tokenFor('manager'));
        $resp = $manager->post('/counters/'.$this->state->counterId.'/approve-and-open', [
            'teller_id' => $this->state->tellerId,
            'approved_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
            'daily_limits' => [
                'USD' => 500000, 'EUR' => 500000, 'GBP' => 500000, 'MYR' => 500000,
            ],
        ]);
        $this->assertSurfaceStatus($resp, 200, "{$label} API approve-and-open");

        $this->assertCounterSessionOpen($this->state->counterId, "{$label} API");
    }

    /**
     * Close the open session via the real API close route so the counter can
     * be reopened. The closing floats mirror the opening floats, so the
     * variance is zero and no supervisor approval is needed.
     */
    private function itClosesCounter(): void
    {
        $manager = $this->newApiClient($this->tokenFor('manager'));
        $resp = $manager->post('/counters/'.$this->state->counterId.'/close', [
            'closing_floats' => ['USD' => 100000, 'EUR' => 100000, 'GBP' => 100000, 'MYR' => 100000],
            'notes' => 'Wave A close for reopen',
        ]);

        $this->assertSurfaceStatus($resp, 200, 'A2b precondition: API counter close');

        $open = $this->state->oracle->scalar(
            "SELECT COUNT(*) FROM counter_sessions WHERE status = 'open'"
        );

        $this->assertSame(0, (int) $open, 'A2b precondition: no counter session should remain open');
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
