<?php

namespace Tests\Http\Simulation\WaveB;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Http\Simulation\Support\SimulationTestCase;
use Tests\Http\Simulation\WaveB\Steps\AttackSteps;
use Tests\Http\Simulation\WaveB\Steps\EdgeSteps;

/**
 * Wave B — edge & attack sweep.
 *
 * Unlike Wave A's single ordered business-day narrative, each scenario is an
 * independent test method (the per-test truncate in SimulationTestCase
 * guarantees a fresh identity graph): the harness submits hostile or invalid
 * input through the real web/API routes and asserts both the HTTP rejection
 * and, via the oracle, that no state was written.
 */
#[Group('wave-b')]
class EdgeAndAttackTest extends SimulationTestCase
{
    use AttackSteps;
    use EdgeSteps;

    #[Test]
    public function it_rejects_unauthenticated_api_requests(): void
    {
        $this->itRejectsUnauthenticatedApiRequests();
    }

    #[Test]
    public function it_rejects_unauthenticated_web_requests(): void
    {
        if (! $this->surfaceAllows('web')) {
            $this->markTestSkipped('web surface not selected');
        }

        $this->itRejectsUnauthenticatedWebRequests();
    }

    #[Test]
    public function it_rejects_forged_session_cookies(): void
    {
        if (! $this->surfaceAllows('web')) {
            $this->markTestSkipped('web surface not selected');
        }

        $this->itRejectsForgedSessionCookie();
    }

    #[Test]
    public function it_rejects_teller_self_approval(): void
    {
        $this->itRejectsTellerSelfApproval();
    }

    #[Test]
    public function it_rejects_same_manager_cancellation_approval(): void
    {
        if (! $this->surfaceAllows('web')) {
            $this->markTestSkipped('web surface not selected');
        }

        $this->itRejectsSameManagerCancellationApproval();
    }

    #[Test]
    public function it_deduplicates_idempotent_bookings(): void
    {
        if (! $this->surfaceAllows('web')) {
            $this->markTestSkipped('web surface not selected');
        }

        $this->itDeduplicatesIdempotentBooking();
    }

    #[Test]
    public function it_rejects_invalid_booking_payloads(): void
    {
        $this->itRejectsInvalidBookingPayloads();
    }

    #[Test]
    public function it_blocks_deviating_rates(): void
    {
        $this->itBlocksDeviatingRates();
    }

    #[Test]
    public function it_rejects_booking_on_a_closed_counter(): void
    {
        $this->itRejectsBookingOnClosedCounter();
    }

    #[Test]
    public function it_rejects_unrequested_cancellation_approval(): void
    {
        if (! $this->surfaceAllows('web')) {
            $this->markTestSkipped('web surface not selected');
        }

        $this->itRejectsUnrequestedCancellationApproval();
    }
}
