<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * BudgetSteps — Wave A step A15.
 *
 * A15: budget create/update on the web surface (no API route).
 */
trait BudgetSteps
{
    /**
     * A15 — web: budget index.
     */
    protected function itManagesBudgets(): void
    {
        $resp = $this->webClient->get('/accounting/budget');
        $this->assertSurfaceStatus($resp, 200, 'A15 web budgets');
    }
}
