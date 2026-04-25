<?php

namespace Tests\Unit;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\BranchPoolService;
use App\Services\MathService;
use App\Services\TellerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TellerAllocationService $service;

    protected BranchPoolService $branchPoolService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchPoolService = new BranchPoolService;
        $this->service = new TellerAllocationService($this->branchPoolService, new MathService);
    }

    public function test_request_allocation_creates_pending(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '50000.0000',
        ]);
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);

        $allocation = $this->service->requestAllocation($teller, $manager, 'MYR', '10000.0000');

        $this->assertInstanceOf(TellerAllocation::class, $allocation);
        $this->assertEquals(TellerAllocationStatus::PENDING, $allocation->status);
        $this->assertEquals('10000.0000', $allocation->requested_amount);
    }

    public function test_approve_allocation_deducts_from_pool(): void
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
        $this->assertEquals(TellerAllocationStatus::APPROVED, $allocation->status);
        $this->assertEquals('10000.0000', $allocation->allocated_amount);
        $this->assertEquals('10000.0000', $allocation->current_balance);
    }

    public function test_activate_allocation(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::APPROVED,
            'allocated_amount' => '10000.0000',
            'current_balance' => '10000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $this->service->activateAllocation($allocation);

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
        $this->assertNotNull($allocation->opened_at);
    }

    public function test_return_to_pool_returns_balance(): void
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
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '8000.0000',
            'allocated_amount' => '10000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $this->service->returnToPool($allocation);

        $pool->refresh();
        $allocation->refresh();
        $this->assertEquals('48000.0000', $pool->available_balance);
        $this->assertEquals(TellerAllocationStatus::RETURNED, $allocation->status);
    }

    public function test_validate_transaction_buy_insufficient_balance(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '5000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'MYR', '10000.0000', true);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Insufficient allocation balance', $result['reason']);
    }

    public function test_validate_transaction_daily_limit_exceeded(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '50000.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '9000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'MYR', '2000.0000', true);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Daily limit exceeded', $result['reason']);
    }

    public function test_validate_transaction_success(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '50000.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'MYR', '5000.0000', true);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('allocation', $result);
    }

    public function test_sell_without_allocation_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '0.0000',
            'daily_limit_myr' => '10000.0000',
            'daily_used_myr' => '0.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->validateTransaction($teller, 'USD', '1000.0000', false);

        $this->assertFalse($result['valid']);
        $this->assertEquals('No USD balance available to sell', $result['reason']);
    }

    public function test_force_return_all_open(): void
    {
        $branch = Branch::factory()->create();
        $pool = BranchPool::factory()->for($branch)->myr()->create([
            'available_balance' => '30000.0000',
            'allocated_balance' => '20000.0000',
        ]);
        $teller1 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $teller2 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);

        $allocation1 = TellerAllocation::factory()->create([
            'user_id' => $teller1->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '8000.0000',
            'allocated_amount' => '10000.0000',
            'session_date' => now()->subDay()->toDateString(),
        ]);
        $allocation2 = TellerAllocation::factory()->create([
            'user_id' => $teller2->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '12000.0000',
            'allocated_amount' => '10000.0000',
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $count = $this->service->forceReturnAllOpen();

        $this->assertEquals(2, $count);
        $pool->refresh();
        $this->assertEquals('50000.0000', $pool->available_balance);
    }

    public function test_transfer_to_teller(): void
    {
        $branch = Branch::factory()->create();
        $teller1 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $teller2 = User::factory()->create(['role' => 'teller', 'branch_id' => $branch->id]);
        $allocation = TellerAllocation::factory()->create([
            'user_id' => $teller1->id,
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'status' => TellerAllocationStatus::ACTIVE,
            'current_balance' => '8000.0000',
            'allocated_amount' => '10000.0000',
            'session_date' => now()->toDateString(),
        ]);

        $result = $this->service->transferToTeller($allocation, $teller2);

        $this->assertEquals($teller2->id, $result->user_id);
    }
}
