<?php

namespace Tests\Http\Simulation\WaveC;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Http\Simulation\Support\SimulationTestCase;
use Tests\Http\Simulation\WaveC\Steps\ParitySteps;

/**
 * Wave C — cross-surface parity.
 *
 * Runs the same business workflow on the web surface (session + CSRF) and
 * on the API v1 surface (Sanctum + JSON), then diffs the derived DB state.
 * Parity means both surfaces write identical truth: same transaction
 * semantics, same double-entry journal, same currency position movement.
 */
#[Group('wave-c')]
class CrossSurfaceParityTest extends SimulationTestCase
{
    use ParitySteps;

    #[Test]
    public function booking_and_approval_write_identical_state_on_both_surfaces(): void
    {
        $web = $this->runBookingWorkflowOnWeb();
        $api = $this->runBookingWorkflowOnApi();

        $this->assertSame(
            $web['transaction'],
            $api['transaction'],
            'Transaction semantics differ between surfaces: web='.json_encode($web['transaction'])
            .' api='.json_encode($api['transaction'])
        );

        $this->assertSame(
            $web['journal'],
            $api['journal'],
            'Journal lines differ between surfaces: web='.json_encode($web['journal'])
            .' api='.json_encode($api['journal'])
        );

        $this->assertSame(
            $web['position_delta'],
            $api['position_delta'],
            'Currency position deltas differ between surfaces: web='.json_encode($web['position_delta'])
            .' api='.json_encode($api['position_delta'])
        );
    }
}
