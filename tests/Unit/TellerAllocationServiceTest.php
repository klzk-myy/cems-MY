<?php

namespace Tests\Unit;

use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\AllocationValidationException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\System\CacheOptimizationService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TellerAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TellerAllocationService $service;

    protected BranchPoolService $branchPoolService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchPoolService = new BranchPoolService(new AuditService(new CacheOptimizationService), new MathService);
        $this->service = new TellerAllocationService($this->branchPoolService, new MathService, app(AuditService::class), app(TillService::class));
    }

    #[Test]
    public function request_allocation_creates_pending(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);

        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');

        $this->assertInstanceOf(TellerAllocation::class, $allocation);
        $this->assertEquals(TellerAllocationStatus::Pending, $allocation->status);
        $this->assertEquals('10000.0000', $allocation->requested_quantity);
    }

    #[Test]
    public function approve_allocation_deducts_from_pool(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');

        $this->service->approveAllocation($allocation, $manager, '10000.0000', '50000.0000');

        $pool->refresh();
        $allocation->refresh();
        $this->assertEquals('40000.0000', $pool->available_balance);
        $this->assertEquals(TellerAllocationStatus::Approved, $allocation->status);
        $this->assertEquals('10000.0000', $allocation->allocated_quantity);
        $this->assertEquals('10000.0000', $allocation->current_quantity);
    }

    #[Test]
    public function activate_allocation(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Approved,
            'allocated_quantity' => '10000.0000',
            'current_quantity' => '10000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $this->service->activateAllocation($allocation);

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::Active, $allocation->status);
        $this->assertNotNull($allocation->opened_at);
    }

    #[Test]
    public function return_to_pool_returns_balance(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '40000.0000',
            'allocated_balance' => '10000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '8000.0000',
            'allocated_quantity' => '10000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $this->service->returnToPool($allocation);

        $pool->refresh();
        $allocation->refresh();
        $this->assertEquals('48000.0000', $pool->available_balance);
        $this->assertEquals(TellerAllocationStatus::Returned, $allocation->status);
    }

    #[Test]
    public function validate_transaction_buy_uses_daily_limit_not_float_balance(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '5000.0000', // smaller than the requested amount
            'daily_limit_myr' => '20000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        // A Buy ADDS to the teller's float, so the float balance must not gate it.
        $result = $this->service->validateTransaction($teller, 'MYR', '10000.0000', true);

        $this->assertTrue($result->valid);

        // ...but exceeding the daily MYR limit still rejects it.
        $result2 = $this->service->validateTransaction($teller, 'MYR', '21000.0000', true);
        $this->assertFalse($result2->valid);
        $this->assertEquals('Daily limit exceeded', $result2->reason);
    }

    #[Test]
    public function validate_transaction_daily_limit_exceeded(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '50000.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '9000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'MYR', '2000.0000', true);

        $this->assertFalse($result->valid);
        $this->assertEquals('Daily limit exceeded', $result->reason);
    }

    #[Test]
    public function validate_transaction_success(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '50000.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'MYR', '5000.0000', true);

        $this->assertTrue($result->valid);
        $this->assertNotNull($result->allocation);
    }

    #[Test]
    public function sell_without_allocation_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '0.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'USD', '1000.0000', false);

        $this->assertFalse($result->valid);
        $this->assertEquals('No USD balance available to sell', $result->reason);
    }

    #[Test]
    public function active_allocation_prefers_funded_over_depleted(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);

        // Depleted allocation created first — an unordered first() would
        // pick it up and wrongly report no balance while a funded
        // allocation for the same day/currency exists.
        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '0.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);
        $funded = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '3000.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        $active = $this->service->getActiveAllocation($teller, 'USD');

        $this->assertSame($funded->id, $active->id);

        $result = $this->service->validateTransaction($teller, 'USD', '200.0000', false, '200.0000');
        $this->assertTrue($result->valid);
    }

    #[Test]
    public function active_allocation_still_returns_depleted_when_it_is_the_only_one(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);

        TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '0.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        // The depleted allocation is returned so the sell validation reports
        // a balance error rather than "no active allocation".
        $this->assertNotNull($this->service->getActiveAllocation($teller, 'USD'));

        $result = $this->service->validateTransaction($teller, 'USD', '200.0000', false, '200.0000');
        $this->assertFalse($result->valid);
        $this->assertEquals('No USD balance available to sell', $result->reason);
    }

    #[Test]
    public function reject_allocation_releases_pool_balance(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');

        $this->service->rejectAllocation($allocation, $manager, 'Insufficient documentation');

        $pool->refresh();
        $allocation->refresh();
        $this->assertEquals('50000.0000', $pool->available_balance);
        $this->assertEquals('0.0000', $pool->allocated_balance);
        $this->assertEquals(TellerAllocationStatus::Rejected, $allocation->status);
        $this->assertNotNull($allocation->rejected_at);
        $this->assertEquals($manager->id, $allocation->rejected_by);
        $this->assertEquals('Insufficient documentation', $allocation->rejection_reason);
    }

    #[Test]
    public function transfer_to_teller(): void
    {
        $branch = Branch::factory()->create();
        $teller1 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $teller2 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller1->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Active,
            'current_quantity' => '8000.0000',
            'allocated_quantity' => '10000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->transferToTeller($allocation, $teller2);

        $this->assertEquals($teller2->id, $result->user_id);

        $this->assertDatabaseHas('system_logs', [
            'action' => 'teller_allocation_transferred',
            'entity_id' => $allocation->id,
        ]);
    }

    #[Test]
    public function transfer_to_teller_rejects_cross_branch_target(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $teller1 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $teller2 = User::factory()->create(['role' => 'teller', 'branch_id' => $otherBranch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller1->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::Active,
            'session_date' => now()->toDateString(),
        ]);

        $this->expectException(AllocationValidationException::class);

        $this->service->transferToTeller($allocation, $teller2);
    }

    #[Test]
    public function request_allocation_writes_audit_record(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $approver = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);

        BranchPool::factory()->create([
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'available_balance' => '10000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $allocation = $this->service->requestAllocation($teller, $approver, 'MYR', '5000.0000');

        $this->assertNotNull($allocation->id);
        $this->assertDatabaseHas('system_logs', [
            'action' => 'teller_allocation_requested',
            'entity_id' => $allocation->id,
        ]);
    }

    #[Test]
    public function allocation_calculations_use_math_service_precision(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '100000.0000',
            'allocated_balance' => '0.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);

        // Create an approved allocation
        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');
        $this->service->approveAllocation($allocation, $manager, '10000.0000', '50000.0000');
        $this->service->activateAllocation($allocation);
        $allocation->refresh();

        // Test increase using modifyAllocation
        $this->branchPoolService->allocateToTeller($branch, 'MYR', '5000.0000');
        $this->service->modifyAllocation($allocation, $manager, '5000.0000', true);
        $allocation->refresh();

        $this->assertEquals('15000.0000', $allocation->current_quantity);
        $this->assertEquals('15000.0000', $allocation->allocated_quantity);

        // Test decrease using modifyAllocation
        $this->service->modifyAllocation($allocation, $manager, '3000.0000', false);
        $allocation->refresh();

        // When decreasing, allocated_quantity decreases but current_quantity decreases by (newAmount - returnAmount)
        // if newAmount > availableToReturn, returnAmount = availableToReturn
        // availableToReturn = allocated - current_quantity = 15000 - 15000 = 0
        // So returnAmount = 0, current_quantity = 15000 - (3000 - 0) = 12000
        $this->assertEquals('12000.0000', $allocation->current_quantity);
        $this->assertEquals('12000.0000', $allocation->allocated_quantity);
    }
}
