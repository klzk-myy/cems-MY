<?php

namespace Tests\Feature;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\BranchPoolService;
use App\Services\CounterOpeningWorkflowService;
use App\Services\CounterService;
use App\Services\MathService;
use App\Services\TellerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAllocationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected BranchPool $pool;

    protected Counter $counter;

    protected Currency $currency;

    protected User $manager;

    protected User $tellerA;

    protected User $tellerB;

    protected BranchPoolService $branchPoolService;

    protected TellerAllocationService $tellerAllocationService;

    protected CounterOpeningWorkflowService $workflowService;

    protected CounterService $counterService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::where('code', 'USD')->firstOrFail();

        $this->branch = Branch::create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        $this->pool = BranchPool::create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '100000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $this->counter = Counter::create([
            'name' => 'Test Counter 1',
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->tellerA = User::create([
            'username' => 'tellerA'.substr(uniqid(), -6),
            'email' => 'tellerA-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->tellerB = User::create([
            'username' => 'tellerB'.substr(uniqid(), -6),
            'email' => 'tellerB-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $mathService = new MathService;
        $branchPoolService = new BranchPoolService;
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService);
        $this->branchPoolService = $branchPoolService;
        $this->tellerAllocationService = $tellerAllocationService;
        $counterService = new CounterService($tellerAllocationService);
        $this->counterService = $counterService;
        $this->workflowService = new CounterOpeningWorkflowService(
            $branchPoolService,
            $tellerAllocationService,
            $counterService
        );
    }

    /** @test */
    public function test_full_teller_opening_workflow(): void
    {
        $requestAmount = '50000.0000';

        $requests = $this->workflowService->initiateOpeningRequest(
            $this->tellerA,
            $this->counter,
            ['USD' => $requestAmount]
        );

        $this->assertCount(1, $requests);
        $allocation = $requests[0];
        $this->assertEquals(TellerAllocationStatus::PENDING, $allocation->status);
        $this->assertEquals($this->tellerA->id, $allocation->user_id);

        $approvedAmount = '45000.0000';
        $dailyLimit = '200000.0000';

        $session = $this->workflowService->approveAndOpen(
            $this->manager,
            $this->counter,
            $this->tellerA,
            ['USD' => $approvedAmount],
            ['USD' => $dailyLimit]
        );

        $this->assertNotNull($session);
        $this->assertEquals(CounterSessionStatus::Open, $session->status);
        $this->assertEquals($this->tellerA->id, $session->user_id);
        $this->assertEquals($this->counter->id, $session->counter_id);

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
        $this->assertEquals($this->counter->id, $allocation->counter_id);

        $this->pool->refresh();
        $this->assertEquals('55000.0000', $this->pool->available_balance);
        $this->assertEquals('45000.0000', $this->pool->allocated_balance);
    }

    /** @test */
    public function test_eod_return_workflow(): void
    {
        $approvedAmount = '40000.0000';
        $dailyLimit = '150000.0000';

        $requests = $this->workflowService->initiateOpeningRequest(
            $this->tellerA,
            $this->counter,
            ['USD' => '50000.0000']
        );

        $session = $this->workflowService->approveAndOpen(
            $this->manager,
            $this->counter,
            $this->tellerA,
            ['USD' => $approvedAmount],
            ['USD' => $dailyLimit]
        );

        $allocation = TellerAllocation::where('user_id', $this->tellerA->id)
            ->where('currency_code', 'USD')
            ->first();

        $this->pool->refresh();
        $allocatedBefore = $this->pool->allocated_balance;

        $this->tellerAllocationService->returnToPool($allocation);

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::RETURNED, $allocation->status);
        $this->assertNotNull($allocation->closed_at);

        $this->pool->refresh();
        $this->assertEquals('100000.0000', $this->pool->available_balance);
        $this->assertEquals('0.0000', $this->pool->allocated_balance);
    }

    /** @test */
    public function test_handover_workflow(): void
    {
        $approvedAmount = '35000.0000';

        $requests = $this->workflowService->initiateOpeningRequest(
            $this->tellerA,
            $this->counter,
            ['USD' => '50000.0000']
        );

        $session = $this->workflowService->approveAndOpen(
            $this->manager,
            $this->counter,
            $this->tellerA,
            ['USD' => $approvedAmount],
            ['USD' => '150000.0000']
        );

        $allocation = TellerAllocation::where('user_id', $this->tellerA->id)
            ->where('currency_code', 'USD')
            ->first();

        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
        $this->assertEquals($this->tellerA->id, $allocation->user_id);

        $result = $this->counterService->initiateHandover(
            $session,
            $this->tellerA,
            $this->tellerB,
            $this->manager,
            [['currency_id' => 'USD', 'amount' => $approvedAmount]]
        );

        $this->assertArrayHasKey('handover', $result);
        $this->assertArrayHasKey('new_session', $result);
        $this->assertEquals(CounterSessionStatus::HandedOver, $session->fresh()->status);
        $this->assertEquals($this->tellerB->id, $result['new_session']->user_id);

        $allocation->refresh();
        $this->assertEquals($this->tellerB->id, $allocation->user_id);
        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
    }
}
