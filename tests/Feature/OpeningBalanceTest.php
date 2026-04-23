<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected Branch $branch;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected ChartOfAccount $cashAccount;

    protected ChartOfAccount $equityAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
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

        $this->fiscalYear = FiscalYear::create([
            'year_code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'Open',
        ]);

        $this->period = AccountingPeriod::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_code' => '2026-01',
            'period_type' => 'month',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $this->cashAccount = ChartOfAccount::firstOrCreate(
            ['account_code' => '9999'],
            ['account_name' => 'Cash - MYR (Test)', 'account_type' => 'Asset', 'is_active' => true]
        );

        $this->equityAccount = ChartOfAccount::firstOrCreate(
            ['account_code' => '4000'],
            ['account_name' => 'Paid-in Capital', 'account_type' => 'Equity', 'is_active' => true]
        );
    }

    /** @test */
    public function manager_can_view_opening_balance_create_form(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/accounting/opening-balance/create');

        $response->assertStatus(200);
        $response->assertViewIs('accounting.opening_balance.create');
        $response->assertViewHas('branches');
        $response->assertViewHas('fiscalYears');
        $response->assertViewHas('assetAccounts');
    }

    /** @test */
    public function non_manager_cannot_view_opening_balance_create_form(): void
    {
        $teller = User::create([
            'username' => 'teller'.substr(uniqid(), -6),
            'email' => 'teller-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Teller,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($teller)
            ->get('/accounting/opening-balance/create');

        $response->assertStatus(403);
    }

    /** @test */
    public function manager_can_create_opening_balance_entry(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
                'reference_number' => 'OB-2026-001',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $entry = JournalEntry::where('reference_type', 'Opening Balance')->first();
        $this->assertNotNull($entry);
        $this->assertEquals('Posted', $entry->status->value);
        $this->assertEquals($this->period->id, $entry->period_id);
        $this->assertEquals('OB-2026-001', $entry->entry_number);

        $lines = $entry->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('account_code', $this->cashAccount->account_code);
        $this->assertNotNull($debitLine);
        $this->assertEquals(10000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        $creditLine = $lines->firstWhere('account_code', $this->equityAccount->account_code);
        $this->assertNotNull($creditLine);
        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(10000.00, (float) $creditLine->credit);
    }

    /** @test */
    public function opening_balance_entry_requires_valid_branch(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => 99999,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
            ]);

        $response->assertSessionHasErrors('branch_id');
    }

    /** @test */
    public function opening_balance_entry_requires_valid_fiscal_year(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => 99999,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
            ]);

        $response->assertSessionHasErrors('fiscal_year_id');
    }

    /** @test */
    public function opening_balance_entry_requires_valid_period(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => 99999,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
            ]);

        $response->assertSessionHasErrors('period_id');
    }

    /** @test */
    public function opening_balance_entry_requires_valid_account(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => '8888',
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
            ]);

        $response->assertSessionHasErrors('debit_account_code');
    }

    /** @test */
    public function opening_balance_entry_requires_positive_amount(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '0.00',
                'description' => 'Initial capital injection',
            ]);

        $response->assertSessionHasErrors('amount');
    }

    /** @test */
    public function opening_balance_entry_is_posted_immediately(): void
    {
        $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
            ]);

        $entry = JournalEntry::where('reference_type', 'Opening Balance')->first();
        $this->assertNotNull($entry);
        $this->assertEquals('Posted', $entry->status->value);
        $this->assertNotNull($entry->posted_at);
        $this->assertNotNull($entry->approved_at);
    }

    /** @test */
    public function opening_balance_entry_without_reference_number_generates_auto_number(): void
    {
        $this->actingAs($this->manager)
            ->post('/accounting/opening-balance', [
                'branch_id' => $this->branch->id,
                'fiscal_year_id' => $this->fiscalYear->id,
                'period_id' => $this->period->id,
                'entry_date' => '2026-01-01',
                'debit_account_code' => $this->cashAccount->account_code,
                'amount' => '10000.00',
                'description' => 'Initial capital injection',
            ]);

        $entry = JournalEntry::where('reference_type', 'Opening Balance')->first();
        $this->assertNotNull($entry);
        $this->assertStringStartsWith('OB-', $entry->entry_number);
    }
}
