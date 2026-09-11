<?php

namespace Tests\Http\Simulation\WaveA\Steps;

/**
 * BranchClosingSteps — Wave A step A10.
 *
 * A10: full branch-closing lifecycle (initiate → settle → finalize) on the
 * web surface. Runs against the idle BR001 branch: HQ's pools, allocations
 * and counter sessions stay intact for the day's remaining steps.
 */
trait BranchClosingSteps
{
    /**
     * A10 — web: drive initiate → settle → finalize and assert the workflow
     * state machine advances at each transition.
     */
    protected function itClosesBranch(): void
    {
        $branchId = (int) $this->state->oracle->scalar(
            "SELECT id FROM branches WHERE code = 'BR001' LIMIT 1"
        );

        $this->assertGreaterThan(0, $branchId, 'A10: BR001 branch must exist');

        // Branch closing routes are role:admin.
        $this->asWebUser('sim_admin', function () use ($branchId): void {
            $resp = $this->webClient->get('/branches/'.$branchId.'/closing');
            $this->assertSurfaceStatus($resp, 200, 'A10 web branch closing page');

            $base = '/branches/'.$branchId.'/closing';

            $resp = $this->webClient->post($base.'/initiate', [
                'branch_id' => $branchId,
                'reason' => 'Wave A end-of-day branch closure scenario.',
                'scheduled_date' => now()->addDay()->toDateString(),
            ]);
            $this->assertSurfaceStatus($resp, 302, 'A10 web initiate');
            $status = $this->state->oracle->scalar(
                'SELECT status FROM branch_closure_workflows WHERE branch_id = ? ORDER BY id DESC LIMIT 1',
                [$branchId]
            );
            $this->assertSame('initiated', $status, 'A10: workflow should be initiated');

            $resp = $this->webClient->post($base.'/settle', [
                'branch_id' => $branchId,
                'amount_myr' => '0',
                'currency_code' => 'MYR',
                'settlement_date' => now()->toDateString(),
            ]);
            $this->assertSurfaceStatus($resp, 302, 'A10 web settle');
            $status = $this->state->oracle->scalar(
                'SELECT status FROM branch_closure_workflows WHERE branch_id = ? ORDER BY id DESC LIMIT 1',
                [$branchId]
            );
            $this->assertSame('settled', $status, 'A10: workflow should be settled');

            $adminId = (int) $this->state->oracle->scalar(
                "SELECT id FROM users WHERE username = 'sim_admin' LIMIT 1"
            );

            $resp = $this->webClient->post($base.'/finalize', [
                'branch_id' => $branchId,
                'completed_at' => now()->toDateTimeString(),
                'finalized_by' => $adminId,
            ]);
            $this->assertSurfaceStatus($resp, 302, 'A10 web finalize');
            $status = $this->state->oracle->scalar(
                'SELECT status FROM branch_closure_workflows WHERE branch_id = ? ORDER BY id DESC LIMIT 1',
                [$branchId]
            );
            $this->assertSame('finalized', $status, 'A10: workflow should be finalized');
        }, 'sim_admin');
    }
}
