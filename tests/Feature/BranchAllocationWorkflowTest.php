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
use App\Services\AuditService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\CounterHandoverService;
use App\Services\Branch\CounterOpeningWorkflowService;
use App\Services\Branch\CounterService;
use App\Services\Branch\HandoverVarianceCalculator;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchAllocationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

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

    protected CounterHandoverService $counterHandoverService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::where('code', 'USD')->firstOrFail();

        $this->branch = Branch::factory()->create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
        ]);

        $this->pool = BranchPool::factory()->create([
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'available_balance' => '100000.0000',
            'allocated_balance' => '0.0000',
        ]);

        $this->counter = Counter::factory()->create([
            'name' => 'Test Counter 1',
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $this->branch->id,
        ]);

        $this->manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
        ]);

        $this->tellerA = User::factory()->create([
            'username' => 'tellerA'.substr(uniqid(), -6),
            'email' => 'tellerA-'.uniqid().'@test.com',
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);

        $this->tellerB = User::factory()->create([
            'username' => 'tellerB'.substr(uniqid(), -6),
            'email' => 'tellerB-'.uniqid().'@test.com',
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
        ]);

        $mathService = new MathService;
        $branchPoolService = new BranchPoolService(new AuditService, $mathService);
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService, app(AuditService::class), app(TillService::class));
        $this->branchPoolService = $branchPoolService;
        $this->tellerAllocationService = $tellerAllocationService;
        $counterService = new CounterService($tellerAllocationService, new ThresholdService, app(AuditService::class));
        $this->counterService = $counterService;
        $this->counterHandoverService = new CounterHandoverService(
            $tellerAllocationService,
            new ThresholdService,
            new HandoverVarianceCalculator,
        );
        $auditService = resolve(AuditService::class);
        $this->workflowService = new CounterOpeningWorkflowService(
            $branchPoolService,
            $tellerAllocationService,
            $counterService,
            $auditService
        );
    }

    #[Test]
    public function full_teller_opening_workflow(): void
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

    #[Test]
    public function eod_return_workflow(): void
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

    #[Test]
    public function handover_workflow(): void
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

        $result = $this->counterHandoverService->initiateHandover(
            $session,
            $this->tellerA,
            $this->tellerB,
            $this->manager,
            [['currency_id' => 'USD', 'amount' => $approvedAmount]]
        );

        $this->assertArrayHasKey('handover', $result);
        $this->assertArrayHasKey('new_session', $result);
        $this->assertEquals(CounterSessionStatus::PendingHandover, $session->fresh()->status);
        $this->assertEquals($this->tellerB->id, $result['new_session']->user_id);

        $allocation->refresh();
        $this->assertEquals($this->tellerB->id, $allocation->user_id);
        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocation->status);
    }

    #[Test]
    public function approve_and_open_finds_pending_allocation_across_date_boundary(): void
    {
        $requestAmount = '50000.0000';

        $requests = $this->workflowService->initiateOpeningRequest(
            $this->tellerA,
            $this->counter,
            ['USD' => $requestAmount]
        );

        $allocation = $requests[0];
        // Simulate allocation from yesterday to test date-boundary tolerance
        $allocation->update(['session_date' => now()->subDay()->toDateString()]);

        $session = $this->workflowService->approveAndOpen(
            $this->manager,
            $this->counter,
            $this->tellerA,
            ['USD' => '45000.0000'],
            ['USD' => '200000.0000']
        );

        $this->assertNotNull($session);
    }

    #[Test]
    public function close_session_and_return_to_pool_credits_branch_pool(): void
    {
        $approvedAmount = '40000.0000';

        $this->workflowService->initiateOpeningRequest(
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

        $this->pool->refresh();
        $this->assertEquals('60000.0000', $this->pool->available_balance);
        $this->assertEquals('40000.0000', $this->pool->allocated_balance);

        $this->counterService->closeSessionAndReturnToPool(
            $session,
            $this->tellerA,
            [['currency_id' => 'USD', 'amount' => $approvedAmount]]
        );

        $allocation = TellerAllocation::where('user_id', $this->tellerA->id)
            ->where('currency_code', 'USD')
            ->first();

        $this->assertEquals(TellerAllocationStatus::RETURNED, $allocation->fresh()->status);

        $this->pool->refresh();
        $this->assertEquals('100000.0000', $this->pool->available_balance);
        $this->assertEquals('0.0000', $this->pool->allocated_balance);
    }

    #[Test]
    public function close_session_releases_stock_still_loaded_in_till(): void
    {
        $approvedAmount = '40000.0000';

        $this->workflowService->initiateOpeningRequest(
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

        // Teller parks part of their custody in the drawer. The session's
        // till opened with the approved float, so the drawer now holds
        // 40,000 float + 10,000 loaded = 50,000 expected at close.
        $this->tellerAllocationService->moveBetweenTillAndAllocation(
            $allocation,
            $session,
            '10000.0000',
            true
        );

        $allocation->refresh();
        $this->assertEquals('30000.0000', $allocation->current_balance);
        $this->assertEquals('10000.0000', $allocation->loaded_balance);

        // A second teller's custody on the same branch must survive the close.
        /** @var TellerAllocation $allocationB */
        $allocationB = TellerAllocation::factory()->create([
            'user_id' => $this->tellerB->id,
            'branch_id' => $this->branch->id,
            'currency_code' => 'USD',
            'status' => TellerAllocationStatus::ACTIVE,
            'allocated_amount' => '5000.0000',
            'current_balance' => '5000.0000',
            'requested_amount' => '5000.0000',
        ]);

        $this->counterService->closeSession(
            $session,
            $this->tellerA,
            [['currency_id' => 'USD', 'amount' => '50000.0000']]
        );

        $allocation->refresh();
        $this->assertEquals(TellerAllocationStatus::RETURNED, $allocation->status);
        $this->assertEquals('0.0000', $allocation->loaded_balance);

        // Custody (30,000) + loaded stock (10,000) both released the earmark.
        $this->pool->refresh();
        $this->assertEquals('100000.0000', $this->pool->available_balance);
        $this->assertEquals('0.0000', $this->pool->allocated_balance);

        $allocationB->refresh();
        $this->assertEquals(TellerAllocationStatus::ACTIVE, $allocationB->status);
    }
}
