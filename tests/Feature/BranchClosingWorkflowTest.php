<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\User;
use App\Services\BranchClosingService;
use App\Services\BranchPoolService;
use App\Services\MathService;
use App\Services\TellerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchClosingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected BranchPool $pool;

    protected Counter $counter;

    protected Currency $currency;

    protected User $manager;

    protected User $tellerA;

    protected BranchClosingService $branchClosingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::where('code', 'USD')->firstOrFail();

        $this->branch = Branch::factory()->create([
            'code' => 'BR'.substr(uniqid(), -4),
            'name' => 'Test Branch',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
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
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->tellerA = User::factory()->create([
            'username' => 'tellerA'.substr(uniqid(), -6),
            'email' => 'tellerA-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->branchClosingService = new BranchClosingService;
    }

    /** @test */
    public function test_initiate_closure_workflow(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->assertNotNull($workflow);
        $this->assertEquals($this->branch->id, $workflow->branch_id);
        $this->assertEquals($this->manager->id, $workflow->initiated_by);
        $this->assertEquals('initiated', $workflow->status);
        $this->assertNull($workflow->finalized_at);
    }

    /** @test */
    public function test_checklist_reflects_actual_branch_state(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $checklist = $this->branchClosingService->getChecklist($workflow);

        $this->assertIsArray($checklist);
        $this->assertArrayHasKey('counters_closed', $checklist);
        $this->assertArrayHasKey('allocations_returned', $checklist);
        $this->assertArrayHasKey('transfers_complete', $checklist);
        $this->assertArrayHasKey('documents_finalized', $checklist);

        $this->assertTrue($checklist['counters_closed'], 'No open counters should mean counters_closed is true');
        $this->assertTrue($checklist['allocations_returned'], 'No active allocations should mean allocations_returned is true');
        $this->assertTrue($checklist['transfers_complete'], 'No pending transfers should mean transfers_complete is true');
        $this->assertTrue($checklist['documents_finalized'], 'No other pending workflows');
    }

    /** @test */
    public function test_can_finalize_when_branch_has_no_pending_items(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->assertTrue($this->branchClosingService->canFinalize($workflow));
    }

    /** @test */
    public function test_finalize_succeeds_when_checklist_complete(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->branchClosingService->finalize($workflow, $this->manager);

        $workflow->refresh();
        $this->assertEquals('finalized', $workflow->status);
        $this->assertNotNull($workflow->finalized_at);
    }

    /** @test */
    public function test_finalize_throws_when_branch_has_pending_items(): void
    {
        $mathService = new MathService;
        $branchPoolService = new BranchPoolService($mathService);
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService);

        $allocation = $tellerAllocationService->requestAllocation(
            $this->tellerA,
            $this->manager,
            'USD',
            '10000.0000'
        );

        $tellerAllocationService->approveAllocation($allocation, $this->manager, '10000.0000');
        $tellerAllocationService->activateAllocation($allocation);

        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->assertFalse($this->branchClosingService->canFinalize($workflow));

        $this->expectException(BranchClosingChecklistIncompleteException::class);
        $this->branchClosingService->finalize($workflow, $this->manager);
    }

    /** @test */
    public function test_get_active_workflow_returns_latest_initiated(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $active = $this->branchClosingService->getActiveWorkflow($this->branch);

        $this->assertNotNull($active);
        $this->assertEquals($workflow->id, $active->id);
    }

    /** @test */
    public function test_get_active_workflow_returns_null_when_no_workflow(): void
    {
        $active = $this->branchClosingService->getActiveWorkflow($this->branch);

        $this->assertNull($active);
    }

    /** @test */
    public function test_get_active_workflow_excludes_finalized(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $workflow->markSettled();
        $workflow->refresh();

        $active = $this->branchClosingService->getActiveWorkflow($this->branch);
        $this->assertNotNull($active);

        $workflow->markFinalized();
        $workflow->refresh();

        $active = $this->branchClosingService->getActiveWorkflow($this->branch);
        $this->assertNull($active);
    }

    /** @test */
    public function test_workflow_status_helpers(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->assertTrue($workflow->isInitiated());
        $this->assertFalse($workflow->isSettled());
        $this->assertFalse($workflow->isFinalized());

        $workflow->markSettled();
        $workflow->refresh();

        $this->assertFalse($workflow->isInitiated());
        $this->assertTrue($workflow->isSettled());
        $this->assertFalse($workflow->isFinalized());

        $workflow->markFinalized();
        $workflow->refresh();

        $this->assertFalse($workflow->isInitiated());
        $this->assertFalse($workflow->isSettled());
        $this->assertTrue($workflow->isFinalized());
    }

    /** @test */
    public function test_api_endpoints_work(): void
    {
        $user = $this->manager;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/branches/{$this->branch->id}/closing/initiate");

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $workflowId = $response->json('data.id');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/branches/{$this->branch->id}/closing/checklist");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'data' => [
                'workflow',
                'checklist' => [
                    'counters_closed',
                    'allocations_returned',
                    'transfers_complete',
                    'documents_finalized',
                ],
                'can_finalize',
            ],
        ]);
    }

    /** @test */
    public function test_api_finalize_with_incomplete_checklist_fails(): void
    {
        $user = $this->manager;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/branches/{$this->branch->id}/closing/initiate");

        $response->assertStatus(201);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/branches/{$this->branch->id}/closing/finalize");

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }
}
