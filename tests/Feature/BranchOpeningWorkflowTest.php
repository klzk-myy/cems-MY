<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod as PeriodModel;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Branch Opening Workflow Integration Tests
 *
 * Tests the complete branch opening workflow:
 * 1. Create branch with details
 * 2. Set up currency pools
 * 3. Create opening balance journal entry
 *
 * These tests verify BNM compliance requirements for branch setup
 * and proper accounting integration.
 */
class BranchOpeningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $managerUser;

    protected Branch $mainBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->managerUser = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->mainBranch = Branch::factory()->create([
            'code' => 'HQ',
            'name' => 'Head Office',
            'type' => 'head_office',
            'is_main' => true,
        ]);

        Currency::factory()->create([
            'code' => 'MYR',
            'name' => 'Malaysian Ringgit',
            'symbol' => 'RM',
            'is_active' => true,
        ]);

        Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_active' => true,
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'year_code' => (string) date('Y'),
            'start_date' => date('Y').'-01-01',
            'end_date' => date('Y').'-12-31',
            'status' => 'Open',
        ]);

        PeriodModel::factory()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_code' => date('Y').'-01',
            'period_type' => 'month',
            'start_date' => date('Y').'-01-01',
            'end_date' => date('Y').'-01-31',
            'status' => 'open',
        ]);
    }

    private function seedChartOfAccounts(): void
    {
        $accounts = [
            ['account_code' => '1010', 'account_name' => 'Cash MYR', 'account_type' => 'Asset', 'is_active' => true],
            ['account_code' => '3000', 'account_name' => 'Owner Equity', 'account_type' => 'Equity', 'is_active' => true],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $account['account_code']],
                $account
            );
        }
    }

    public function test_admin_can_access_branch_opening_wizard(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('branches.open-wizard'));

        $response->assertStatus(200);
        $response->assertViewIs('branch-openings.index');
    }

    public function test_non_admin_cannot_access_branch_opening_wizard(): void
    {
        $response = $this->actingAs($this->managerUser)
            ->get(route('branches.open-wizard'));

        $response->assertStatus(403);
    }

    public function test_step1_branch_creation_form(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('branches.open.step1'));

        $response->assertStatus(200);
        $response->assertViewIs('branch-openings.step1');
        $response->assertViewHas('branchTypes');
        $response->assertViewHas('parentBranches');
    }

    public function test_step1_process_valid_branch_data(): void
    {
        $branchData = [
            'code' => 'BR001',
            'name' => 'Kuala Lumpur Branch',
            'type' => 'branch',
            'address' => '123 Jalan Bukit Bintang',
            'city' => 'Kuala Lumpur',
            'state' => 'WP',
            'postal_code' => '50000',
            'country' => 'Malaysia',
            'phone' => '+603-1234-5678',
            'email' => 'kl@branch.com',
            'is_main' => false,
            'parent_id' => $this->mainBranch->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step1.process'), $branchData);

        $response->assertRedirect();
        $response->assertRedirectContainsRoute('branches.open.step2');

        $this->assertDatabaseHas('branches', [
            'code' => 'BR001',
            'name' => 'Kuala Lumpur Branch',
            'type' => 'branch',
            'is_active' => true,
        ]);
    }

    public function test_step1_process_sets_main_branch_flag(): void
    {
        $branchData = [
            'code' => 'HQ2',
            'name' => 'New Head Office',
            'type' => 'head_office',
            'is_main' => true,
        ];

        $this->actingAs($this->adminUser)
            ->post(route('branches.open.step1.process'), $branchData);

        $newBranch = Branch::where('code', 'HQ2')->first();
        $this->assertTrue($newBranch->is_main);

        $originalMain = Branch::where('id', '!=', $newBranch->id)
            ->where('is_main', true)
            ->first();

        $this->assertNull($originalMain);
    }

    public function test_step1_validates_unique_branch_code(): void
    {
        $branchData = [
            'code' => 'HQ',
            'name' => 'Duplicate Branch',
            'type' => 'branch',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step1.process'), $branchData);

        $response->assertSessionHasErrors('code');
    }

    public function test_step1_validates_required_fields(): void
    {
        $branchData = [
            'code' => '',
            'name' => '',
            'type' => '',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step1.process'), $branchData);

        $response->assertSessionHasErrors(['code', 'name', 'type']);
    }

    public function test_step2_displays_currency_pools(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Branch',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('branches.open.step2', $branch));

        $response->assertStatus(200);
        $response->assertViewIs('branch-openings.step2');
        $response->assertViewHas('currencies');
        $response->assertViewHas('branch', $branch);
    }

    public function test_step2_creates_branch_pools(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Branch',
        ]);

        $poolData = [
            'pool_MYR' => '10000.00',
            'pool_USD' => '5000.00',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step2.process', $branch), $poolData);

        $response->assertRedirect();

        $this->assertDatabaseHas('branch_pools', [
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'available_balance' => '10000.00',
        ]);

        $this->assertDatabaseHas('branch_pools', [
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'available_balance' => '5000.00',
        ]);
    }

    public function test_step3_displays_opening_balance_form(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Branch',
        ]);

        BranchPool::factory()->create([
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'available_balance' => '10000.00',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('branches.open.step3', $branch));

        $response->assertStatus(200);
        $response->assertViewIs('branch-openings.step3');
        $response->assertViewHas('branch', $branch);
        $response->assertViewHas('totalPoolAmount');
    }

    public function test_step3_creates_opening_balance_journal_entry(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Branch',
        ]);

        $openingData = [
            'amount' => '10000.00',
            'reference' => 'Opening balance for TEST',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step3.process', $branch), $openingData);

        $response->assertStatus(302);

        $journalEntry = JournalEntry::where('reference_type', 'Opening Balance')
            ->first();

        $this->assertNotNull($journalEntry);
    }

    public function test_step3_journal_entry_has_correct_debits_credits(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Branch',
        ]);

        $openingData = [
            'amount' => '10000.00',
            'reference' => 'Opening balance for TEST',
        ];

        $this->actingAs($this->adminUser)
            ->post(route('branches.open.step3.process', $branch), $openingData);

        $journalEntry = JournalEntry::where('reference_type', 'Opening Balance')->first();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journalEntry->id,
            'account_code' => '1010',
            'debit' => '10000.00',
            'credit' => '0',
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $journalEntry->id,
            'account_code' => '3000',
            'debit' => '0',
            'credit' => '10000.00',
        ]);
    }

    public function test_complete_page_shafter_branch_opening(): void
    {
        $branch = Branch::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Branch',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('branches.open.complete', $branch));

        $response->assertStatus(200);
        $response->assertViewIs('branch-openings.complete');
        $response->assertViewHas('branch', $branch);
        $response->assertSee($branch->name);
        $response->assertSee($branch->code);
    }

    public function test_full_branch_opening_workflow(): void
    {
        $branchData = [
            'code' => 'BR002',
            'name' => 'Penang Branch',
            'type' => 'branch',
            'city' => 'Penang',
            'state' => 'Penang',
            'country' => 'Malaysia',
        ];

        $poolData = [
            'pool_MYR' => '50000.00',
            'pool_USD' => '10000.00',
        ];

        $openingData = [
            'amount' => '50000.00',
            'reference' => 'Initial capital for Penang Branch',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step1.process'), $branchData);

        $branch = Branch::where('code', 'BR002')->first();
        $this->assertNotNull($branch);

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step2.process', $branch), $poolData);

        $this->assertDatabaseHas('branch_pools', [
            'branch_id' => $branch->id,
            'currency_code' => 'MYR',
            'available_balance' => '50000.00',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('branches.open.step3.process', $branch), $openingData);

        $response->assertRedirect();

        $journalEntry = JournalEntry::where('reference_type', 'Opening Balance')->first();
        $this->assertNotNull($journalEntry);
        $this->assertEquals('Posted', $journalEntry->status->value);
    }

    public function test_branch_creation_creates_branch(): void
    {
        $branchData = [
            'code' => 'BR003',
            'name' => 'Audit Test Branch',
            'type' => 'branch',
        ];

        $this->actingAs($this->adminUser)
            ->post(route('branches.open.step1.process'), $branchData);

        $this->assertDatabaseHas('branches', [
            'code' => 'BR003',
            'name' => 'Audit Test Branch',
        ]);
    }
}
