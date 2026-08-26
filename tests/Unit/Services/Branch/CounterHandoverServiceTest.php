<?php

namespace Tests\Unit\Services\Branch;

use App\Enums\CounterSessionStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\InvalidStateException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\User;
use App\Services\Branch\CounterHandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounterHandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    private CounterHandoverService $service;

    private Branch $branch;

    private Counter $counter;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CounterHandoverService::class);

        $this->branch = Branch::factory()->create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter 1',
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function find_pending_handover_returns_null_when_none_exists(): void
    {
        $result = $this->service->findPendingHandover(
            userId: 1,
            counterId: 1,
            date: now()->toDateString()
        );

        $this->assertNull($result);
    }

    /**
     * Create a pending handover whose session is PendingHandover.
     *
     * @return array{0: CounterHandover, 1: CounterSession}
     */
    private function createPendingHandover(): array
    {
        $teller1 = User::factory()->create([
            'username' => 'teller1'.substr(uniqid(), -6),
            'email' => 'teller1-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $session = CounterSession::factory()->create([
            'counter_id' => $this->counter->id,
            'user_id' => $teller1->id,
            'session_date' => now()->toDateString(),
            'opened_at' => now()->subMinutes(45),
            'opened_by' => $teller1->id,
            'status' => CounterSessionStatus::PendingHandover,
        ]);

        $handover = CounterHandover::factory()->create([
            'counter_session_id' => $session->id,
            'from_user_id' => $teller1->id,
            'to_user_id' => $this->manager->id,
            'supervisor_id' => $this->manager->id,
            'handover_time' => now(),
            'physical_count_verified' => true,
            'variance_myr' => '0.00',
        ]);

        return [$handover, $session];
    }

    /**
     * D1: once one acknowledgment has committed, a second actor holding a
     * stale (pre-commit) model instance must be rejected by the state
     * re-validation performed inside the transaction/row locks.
     */
    #[Test]
    public function second_acknowledgment_with_stale_instance_is_rejected(): void
    {
        [$handover, $session] = $this->createPendingHandover();

        // First actor wins.
        $this->service->acknowledgeHandover($handover, $this->manager, true, null);

        $handover->refresh();
        $this->assertNotNull($handover->acknowledged_at);

        // Second actor still holds the pre-commit instance (acknowledged_at
        // null, session status cached as PendingHandover) - simulate two
        // simultaneous acknowledgments racing on the same row.
        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Handover is not pending acknowledgment');

        try {
            $this->service->acknowledgeHandover($handover, $this->manager, true, null);
        } catch (InvalidStateException $e) {
            // The losing transaction must have mutated nothing.
            $session->refresh();
            $this->assertSame(CounterSessionStatus::HandedOver, $session->status);

            throw $e;
        }
    }

    /**
     * D1: pending-state validation happens INSIDE the transaction after
     * re-reading rows under lock - not from the caller's possibly stale
     * eager-loaded relation.
     */
    #[Test]
    public function stale_session_status_in_memory_does_not_bypass_revalidation(): void
    {
        [$handover, $session] = $this->createPendingHandover();

        // Load the handover WITH its session while still pending, exactly as
        // both controllers do.
        $stale = CounterHandover::query()->with('counterSession')->whereKey($handover->id)->first();
        $this->assertInstanceOf(CounterHandover::class, $stale);
        $this->assertSame(CounterSessionStatus::PendingHandover, $stale->counterSession->status);

        // Another process commits a state change behind our back.
        $session->update(['status' => CounterSessionStatus::HandedOver]);

        try {
            $this->service->acknowledgeHandover($stale, $this->manager, true, null);
            $this->fail('Stale pending state must not allow acknowledgment');
        } catch (InvalidStateException $e) {
            $this->assertSame('Handover is not pending acknowledgment', $e->getMessage());
        }

        $handover->refresh();
        $this->assertNull($handover->acknowledged_at);
    }
}
