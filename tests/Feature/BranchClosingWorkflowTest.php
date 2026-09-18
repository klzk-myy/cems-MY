<?php

namespace Tests\Feature;

use App\Enums\BranchClosureStatus;
use App\Enums\TellerAllocationStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Exceptions\Domain\BusinessDateFrozenException;
use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\BranchPool;
use App\Models\ChartOfAccount;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\AuditService;
use App\Services\Branch\BranchClosingService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\CounterService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchClosingWorkflowTest extends TestCase
{
    use DatabaseTransactions;

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

        $this->branchClosingService = $this->app->make(BranchClosingService::class);
    }

    #[Test]
    public function initiate_closure_workflow(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->assertNotNull($workflow);
        $this->assertEquals($this->branch->id, $workflow->branch_id);
        $this->assertEquals($this->manager->id, $workflow->initiated_by);
        $this->assertEquals(BranchClosureStatus::Initiated, $workflow->status);
        $this->assertNull($workflow->finalized_at);
    }

    #[Test]
    public function checklist_reflects_actual_branch_state(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $checklist = $this->branchClosingService->getChecklist($workflow);

        $this->assertIsArray($checklist);
        $this->assertArrayHasKey('counters_closed', $checklist);
        $this->assertArrayHasKey('allocations_returned', $checklist);
        $this->assertArrayHasKey('documents_finalized', $checklist);
        $this->assertArrayNotHasKey('transfers_complete', $checklist);

        $this->assertTrue($checklist['counters_closed'], 'No open counters should mean counters_closed is true');
        $this->assertTrue($checklist['allocations_returned'], 'No active allocations should mean allocations_returned is true');
        $this->assertTrue($checklist['documents_finalized'], 'No other pending workflows');
    }

    #[Test]
    public function can_finalize_when_branch_has_no_pending_items(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->assertTrue($this->branchClosingService->canFinalize($workflow));
    }

    #[Test]
    public function finalize_succeeds_when_checklist_complete(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->branchClosingService->finalize($workflow, $this->manager);

        $workflow->refresh();
        $this->assertEquals(BranchClosureStatus::Finalized, $workflow->status);
        $this->assertNotNull($workflow->finalized_at);
    }

    #[Test]
    public function finalize_throws_when_branch_has_pending_items(): void
    {
        $mathService = new MathService;
        $branchPoolService = new BranchPoolService(new AuditService, $mathService);
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService, app(AuditService::class), app(TillService::class));

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

    #[Test]
    public function get_active_workflow_returns_latest_initiated(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $active = $this->branchClosingService->getActiveWorkflow($this->branch);

        $this->assertNotNull($active);
        $this->assertEquals($workflow->id, $active->id);
    }

    #[Test]
    public function get_active_workflow_returns_null_when_no_workflow(): void
    {
        $active = $this->branchClosingService->getActiveWorkflow($this->branch);

        $this->assertNull($active);
    }

    #[Test]
    public function get_active_workflow_excludes_finalized(): void
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

    #[Test]
    public function workflow_status_helpers(): void
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

    #[Test]
    public function api_endpoints_work(): void
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
                    'documents_finalized',
                ],
                'can_finalize',
            ],
        ]);
    }

    #[Test]
    public function api_finalize_with_incomplete_checklist_fails(): void
    {
        $user = $this->manager;

        // Create an active teller allocation that should be returned before finalization
        $allocation = TellerAllocation::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->tellerA->id,
            'counter_id' => $this->counter->id,
            'status' => TellerAllocationStatus::ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/branches/{$this->branch->id}/closing/initiate");

        $response->assertStatus(201);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/branches/{$this->branch->id}/closing/finalize");

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function finalize_is_idempotent_for_finalized_workflow(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->branchClosingService->finalize($workflow, $this->manager);
        $finalizedAt = $workflow->fresh()->finalized_at;

        $this->branchClosingService->finalize($workflow, $this->manager);

        $workflow->refresh();
        $this->assertEquals(BranchClosureStatus::Finalized, $workflow->status);
        $this->assertEquals($finalizedAt, $workflow->finalized_at);
    }

    #[Test]
    public function finalize_archives_checklist_and_recon_snapshot(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->branchClosingService->finalize($workflow, $this->manager);

        $workflow->refresh();
        $this->assertEquals(BranchClosureStatus::Finalized, $workflow->status);

        $snapshot = $workflow->checklist;
        $this->assertIsArray($snapshot);
        $this->assertTrue($snapshot['results']['counters_closed']);
        $this->assertTrue($snapshot['results']['allocations_returned']);
        $this->assertTrue($snapshot['results']['documents_finalized']);

        $this->assertSame(now()->toDateString(), $snapshot['recon']['date']);
        $this->assertArrayHasKey('totals', $snapshot['recon']);
        $this->assertArrayHasKey('summary', $snapshot['recon']);
    }

    #[Test]
    public function settle_moves_no_funds_and_is_idempotent(): void
    {
        // Daily close is operational only: pool balances stay at the branch
        // and no HQ-transfer journals are posted. Remittance is a separate
        // explicit pool action, not part of the close workflow.
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $journalsBefore = JournalEntry::where('reference_type', 'BranchSettlement')->count();

        $this->branchClosingService->settle($workflow, $this->manager);
        $workflow->refresh();
        $this->assertEquals(BranchClosureStatus::Settled, $workflow->status);

        $this->assertSame($journalsBefore, JournalEntry::where('reference_type', 'BranchSettlement')->count());

        $this->pool->refresh();
        $this->assertEquals('100000.0000', $this->pool->available_balance);
        $this->assertEquals('0.0000', $this->pool->allocated_balance);

        // Second settle must be a no-op.
        $this->branchClosingService->settle($workflow, $this->manager);
        $this->assertEquals(BranchClosureStatus::Settled, $workflow->fresh()->status);
    }

    #[Test]
    public function settle_throws_when_counters_still_open(): void
    {
        $counterService = app(CounterService::class);
        $counterService->openSession($this->counter, $this->tellerA, []);

        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);

        $this->expectException(BranchClosingChecklistIncompleteException::class);
        $this->branchClosingService->settle($workflow, $this->manager);
    }

    #[Test]
    public function settle_cancels_pending_and_approved_requests(): void
    {
        $mathService = new MathService;
        $branchPoolService = new BranchPoolService(new AuditService, $mathService);
        $tellerAllocationService = new TellerAllocationService($branchPoolService, $mathService, app(AuditService::class), app(TillService::class));

        // A pending request holds no pool funds.
        $pending = $tellerAllocationService->requestAllocation(
            $this->tellerA,
            $this->manager,
            'USD',
            '5000.0000'
        );

        // An approved-but-unaccepted request still holds a pool earmark.
        $approved = $tellerAllocationService->requestAllocation(
            $this->tellerA,
            $this->manager,
            'USD',
            '10000.0000'
        );
        $tellerAllocationService->approveAllocation($approved, $this->manager, '10000.0000');

        $this->pool->refresh();
        $this->assertEquals('10000.0000', $this->pool->allocated_balance);
        $this->assertEquals('90000.0000', $this->pool->available_balance);

        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->settle($workflow, $this->manager);

        $pending->refresh();
        $this->assertEquals(TellerAllocationStatus::REJECTED, $pending->status);
        $approved->refresh();
        $this->assertEquals(TellerAllocationStatus::REJECTED, $approved->status);
        $this->assertEquals('Cancelled at branch settlement', $approved->rejection_reason);

        // The approved earmark released back to available.
        $this->pool->refresh();
        $this->assertEquals('0.0000', $this->pool->allocated_balance);
        $this->assertEquals('100000.0000', $this->pool->available_balance);
    }

    #[Test]
    public function finalize_freezes_the_business_date_for_that_branch_only(): void
    {
        $otherBranch = Branch::factory()->create();
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->finalize($workflow, $this->manager);

        $this->assertTrue(BranchClosureWorkflow::freezesDate($this->branch->id, now()->toDateString()));
        $this->assertTrue(BranchClosureWorkflow::freezesDate($this->branch->id, now()->subDay()->toDateString()));
        $this->assertFalse(BranchClosureWorkflow::freezesDate($this->branch->id, now()->addDay()->toDateString()));
        $this->assertFalse(BranchClosureWorkflow::freezesDate($otherBranch->id, now()->toDateString()));
    }

    #[Test]
    public function frozen_date_blocks_counter_sessions_for_that_branch(): void
    {
        $counterService = app(CounterService::class);
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->finalize($workflow, $this->manager);

        try {
            $counterService->openSession($this->counter, $this->tellerA, []);
            $this->fail('openSession should reject a frozen business date');
        } catch (BusinessDateFrozenException $e) {
            $this->assertStringContainsString('business date is closed', $e->getMessage());
        }

        // Another branch is unaffected — standalone operation.
        $otherBranch = Branch::factory()->create();
        $otherCounter = Counter::factory()->create([
            'code' => 'CTR'.substr(uniqid(), -4),
            'branch_id' => $otherBranch->id,
        ]);
        $otherTeller = User::factory()->create([
            'username' => 'tellerB'.substr(uniqid(), -6),
            'email' => 'tellerB-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $otherBranch->id,
            'is_active' => true,
        ]);

        $session = $counterService->openSession($otherCounter, $otherTeller, []);
        $this->assertNotNull($session);
    }

    #[Test]
    public function frozen_date_blocks_branch_journals_but_not_company_wide(): void
    {
        $accountingService = app(AccountingService::class);
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->finalize($workflow, $this->manager);

        ChartOfAccount::firstOrCreate(
            ['account_code' => '1000'],
            ['account_name' => 'Cash', 'account_type' => 'Asset', 'is_active' => true]
        );
        ChartOfAccount::firstOrCreate(
            ['account_code' => '4000'],
            ['account_name' => 'Revenue', 'account_type' => 'Revenue', 'is_active' => true]
        );

        $lines = [
            ['account_code' => '1000', 'debit' => '10.00', 'credit' => '0.00'],
            ['account_code' => '4000', 'debit' => '0.00', 'credit' => '10.00'],
        ];

        try {
            $accountingService->createJournalEntry(
                $lines, 'Manual', null, 'Frozen date entry', now()->toDateString(), $this->manager->id, $this->branch->id
            );
            $this->fail('createJournalEntry should reject a frozen business date');
        } catch (BusinessDateFrozenException $e) {
            $this->assertStringContainsString('business date is closed', $e->getMessage());
        }

        // Company-wide entries are HQ business — they bypass the freeze.
        $entry = $accountingService->createJournalEntry(
            $lines, 'Manual', null, 'HQ entry', now()->toDateString(), $this->manager->id, null
        );
        $this->assertNotNull($entry);
    }

    #[Test]
    public function reopen_unfreezes_the_date_and_allows_trading_again(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->finalize($workflow, $this->manager);
        $this->assertTrue(BranchClosureWorkflow::freezesDate($this->branch->id, now()->toDateString()));

        $admin = User::factory()->create([
            'username' => 'admin'.substr(uniqid(), -6),
            'email' => 'admin-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Admin,
            'branch_id' => null,
            'is_active' => true,
        ]);

        $this->branchClosingService->reopen($workflow, $admin);

        $workflow->refresh();
        $this->assertEquals(BranchClosureStatus::Settled, $workflow->status);
        $this->assertNull($workflow->finalized_at);
        $this->assertFalse(BranchClosureWorkflow::freezesDate($this->branch->id, now()->toDateString()));

        $session = app(CounterService::class)->openSession($this->counter, $this->tellerA, []);
        $this->assertNotNull($session);
    }

    #[Test]
    public function reopen_route_is_limited_to_cross_branch_users(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->finalize($workflow, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('branches.closing.reopen', $this->branch))
            ->assertForbidden();

        $admin = User::factory()->create([
            'username' => 'admin'.substr(uniqid(), -6),
            'email' => 'admin-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Admin,
            'branch_id' => null,
            'is_active' => true,
        ]);

        // auth.session stored the manager's password_hash_web on the first
        // request — flush before switching users or AuthenticateSession
        // logs the second user out.
        $this->flushSession();

        $this->actingAs($admin)
            ->post(route('branches.closing.reopen', $this->branch))
            ->assertRedirect();

        $this->assertEquals(BranchClosureStatus::Settled, $workflow->fresh()->status);
    }

    #[Test]
    public function frozen_date_blocks_a_new_closure_workflow(): void
    {
        $workflow = $this->branchClosingService->initiateClosure($this->branch, $this->manager);
        $this->branchClosingService->finalize($workflow, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('branches.closing.initiate', $this->branch))
            ->assertSessionHas('error');
    }

    #[Test]
    public function sidebar_closing_route_redirects_to_the_users_branch_workflow(): void
    {
        // The navigation links to 'closing.show' with no branch parameter —
        // the redirect route must exist and forward to the manager's branch.
        $this->actingAs($this->manager)
            ->get(route('closing.show'))
            ->assertRedirect(route('branches.closing.show', $this->branch));
    }

    #[Test]
    public function sidebar_closing_route_falls_back_to_an_active_trading_branch_for_hq_users(): void
    {
        // Cross-branch users may legitimately have no home branch — the
        // redirect falls back to the first active trading branch so they
        // can inspect a close workflow.
        $hqUser = User::factory()->create([
            'username' => 'hq'.substr(uniqid(), -6),
            'email' => 'hq-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Admin,
            'branch_id' => null,
            'is_active' => true,
        ]);

        $expected = Branch::where('is_active', true)
            ->where('type', '!=', Branch::TYPE_HEAD_OFFICE)
            ->orderBy('id')
            ->firstOrFail();

        $this->actingAs($hqUser)
            ->get(route('closing.show'))
            ->assertRedirect(route('branches.closing.show', $expected));
    }

    #[Test]
    public function sidebar_closing_route_forbids_a_branch_role_with_no_branch(): void
    {
        // A manager is a branch operating role — an unassigned account is a
        // misconfiguration, so it 403s rather than routing to another branch.
        $orphan = User::factory()->create([
            'username' => 'orphan'.substr(uniqid(), -6),
            'email' => 'orphan-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($orphan)
            ->get(route('closing.show'))
            ->assertForbidden();
    }
}
