<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * StockTransferSteps — Wave A step A11.
 *
 * A11: stock transfer lifecycle on the web surface (no API v1 route).
 */
trait StockTransferSteps
{
    /**
     * A11 — web: stock transfer index.
     */
    protected function itManagesStockTransfers(): void
    {
        $resp = $this->webClient->get('/stock-transfers');
        $this->assertSurfaceStatus($resp, 200, 'A11 web stock transfers');
    }
}
