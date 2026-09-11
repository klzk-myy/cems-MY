<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * AccountingSteps — Wave A steps A9, A17, A18, A32.
 *
 * A9  month-end reports (trial balance + MSB2 export)
 * A17 month-end close (API)
 * A18 revaluation (web)
 * A32 chart of accounts (web)
 */
trait AccountingSteps
{
    /**
     * A9 — web: trial balance; API: MSB2 export.
     */
    protected function itRunsMonthEndReports(): void
    {
        $resp = $this->webClient->get('/accounting/trial-balance');
        $this->assertSurfaceStatus($resp, 200, 'A9 web trial balance');

        $api = $this->newApiClient($this->tokenFor('manager'));
        $resp = $api->post('/reports/msb2', ['date' => now()->toDateString()]);
        $this->assertSurfaceStatus($resp, 200, 'A9 API MSB2 export');
    }

    /**
     * A17 — API: month-end close.
     */
    protected function itClosesMonthEndViaApi(): void
    {
        $api = $this->newApiClient($this->tokenFor('admin'));
        $resp = $api->post('/accounting/month-end/close', []);
        $this->assertContains($resp['status'], [200, 202, 409, 422], 'A17 API month-end close');
    }

    /**
     * A18 — web: revaluation page.
     */
    protected function itRunsRevaluation(): void
    {
        $resp = $this->webClient->get('/accounting/revaluation');
        $this->assertSurfaceStatus($resp, 200, 'A18 web revaluation');
    }

    /**
     * A32 — web: chart of accounts.
     */
    protected function itListsChartOfAccounts(): void
    {
        $resp = $this->webClient->get('/accounting/chart-of-accounts');
        $this->assertContains($resp['status'], [200, 302], 'A32 web chart of accounts');
    }
}
