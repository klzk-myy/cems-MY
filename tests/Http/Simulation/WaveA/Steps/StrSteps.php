<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * StrSteps — Wave A step A22.
 *
 * A22: STR filing on the API surface.
 */
trait StrSteps
{
    /**
     * A22 — API: MSB2/STR report generation.
     */
    protected function itFilesStr(): void
    {
        // MSB2 generation is manager/admin-scoped (role middleware on the
        // reports group), so file with the manager token.
        $api = $this->newApiClient($this->tokenFor('manager'));
        $resp = $api->post('/reports/msb2', ['date' => now()->toDateString()]);
        $this->assertSurfaceStatus($resp, 200, 'A22 API STR filing');
    }
}
