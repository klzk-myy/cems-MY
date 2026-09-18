<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\FiscalYearService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;

    protected Branch $branch;

    protected FiscalYear $fiscalYear;

    protected ChartOfAccount $cashAccount;

    protected ChartOfAccount $revenueAccount;

    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test branch with unique code
        $this->branch = Branch::factory()->create([
            'code' => 'HQ'.substr(uniqid(), -4),
            'name' => 'Test Head Office',
            'address' => '123 Test Street',
            'phone' => '+60312345678',
            'email' => 'test@localhost.com',
            'is_active' => true,
        ]);

        // Create manager user with unique username
        $this->manager = User::factory()->create([
            'username' => 'manager'.substr(uniqid(), -6),
            'email' => 'manager-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Create fiscal year
        $this->fiscalYear = FiscalYear::factory()->create([
            'year_code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'Open',
        ]);

        // Create chart of accounts with unique codes
        $this->cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '9999',
            'account_name' => 'Test Cash',
            'account_type' => 'Asset',
            'is_active' => true,
        ]);

        $this->revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '5999',
            'account_name' => 'Test Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
        ]);

        $this->accountingService = app(AccountingService::class);
    }

    #[Test]
    public function it_can_create_a_journal_entry(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/journal', [
                'entry_date' => now()->format('Y-m-d'),
                'description' => 'Test journal entry',
                'reference_type' => 'Manual',
                'lines' => [
                    [
                        'account_code' => $this->cashAccount->account_code,
                        'debit' => '1000.00',
                        'credit' => '0.00',
                    ],
                    [
                        'account_code' => $this->revenueAccount->account_code,
                        'debit' => '0.00',
                        'credit' => '1000.00',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    #[Test]
    public function accountant_can_post_company_wide_and_branch_scoped_journal_entries(): void
    {
        $accountant = User::factory()->create([
            'username' => 'acct'.substr(uniqid(), -6),
            'email' => 'acct-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Accountant,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // No branch_id — a cross-branch role posts a company-wide entry.
        $response = $this->actingAs($accountant)->post('/accounting/journal', [
            'entry_date' => now()->format('Y-m-d'),
            'description' => 'Accountant company-wide entry',
            'lines' => [
                [
                    'account_code' => $this->cashAccount->account_code,
                    'debit' => '50.00',
                    'credit' => '0.00',
                ],
                [
                    'account_code' => $this->revenueAccount->account_code,
                    'debit' => '0.00',
                    'credit' => '50.00',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull(JournalEntry::latest('id')->first()->branch_id);

        // An explicit branch_id is honoured for cross-branch roles.
        $otherBranch = Branch::factory()->create();
        $response = $this->actingAs($accountant)->post('/accounting/journal', [
            'entry_date' => now()->format('Y-m-d'),
            'description' => 'Accountant branch entry',
            'branch_id' => $otherBranch->id,
            'lines' => [
                [
                    'account_code' => $this->cashAccount->account_code,
                    'debit' => '25.00',
                    'credit' => '0.00',
                ],
                [
                    'account_code' => $this->revenueAccount->account_code,
                    'debit' => '0.00',
                    'credit' => '25.00',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($otherBranch->id, JournalEntry::latest('id')->first()->branch_id);
    }

    #[Test]
    public function branch_scoped_user_journal_is_stamped_with_own_branch(): void
    {
        // A branch-scoped poster cannot smuggle in another branch_id.
        $otherBranch = Branch::factory()->create();

        $response = $this->actingAs($this->manager)->post('/accounting/journal', [
            'entry_date' => now()->format('Y-m-d'),
            'description' => 'Manager branch entry',
            'branch_id' => $otherBranch->id,
            'lines' => [
                [
                    'account_code' => $this->cashAccount->account_code,
                    'debit' => '10.00',
                    'credit' => '0.00',
                ],
                [
                    'account_code' => $this->revenueAccount->account_code,
                    'debit' => '0.00',
                    'credit' => '10.00',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($this->branch->id, JournalEntry::latest('id')->first()->branch_id);
    }

    #[Test]
    public function journal_line_carrying_both_debit_and_credit_is_rejected(): void
    {
        $entryCount = JournalEntry::count();

        $response = $this->actingAs($this->manager)->post('/accounting/journal', [
            'entry_date' => now()->format('Y-m-d'),
            'description' => 'Double-sided line',
            'lines' => [
                [
                    'account_code' => $this->cashAccount->account_code,
                    'debit' => '100.00',
                    'credit' => '100.00',
                ],
                [
                    'account_code' => $this->revenueAccount->account_code,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.debit');
        $response->assertSessionHasErrors('lines.1.debit');
        $this->assertSame($entryCount, JournalEntry::count());
    }

    #[Test]
    public function branch_scoped_manager_cannot_reverse_another_branchs_entry(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherManager = User::factory()->create([
            'username' => 'mgr'.substr(uniqid(), -6),
            'email' => 'mgr-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $otherBranch->id,
            'is_active' => true,
        ]);

        // An entry belonging to the manager's own branch, posted via service.
        $ownEntry = $this->accountingService->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '10.00'],
            ],
            'Manual',
            null,
            'Own branch entry',
            now()->toDateString(),
            $this->manager->id,
            $this->branch->id
        );

        $otherEntry = $this->accountingService->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '10.00'],
            ],
            'Manual',
            null,
            'Other branch entry',
            now()->toDateString(),
            $otherManager->id,
            $otherBranch->id
        );

        // Own branch: allowed.
        $this->actingAs($this->manager)
            ->post("/accounting/journal/{$ownEntry->id}/reverse", ['reason' => 'Correction'])
            ->assertRedirect();

        // Another branch's entry: denied by the policy's branch scoping.
        $this->actingAs($this->manager)
            ->post("/accounting/journal/{$otherEntry->id}/reverse", ['reason' => 'Correction'])
            ->assertForbidden();
    }

    #[Test]
    public function branch_scoped_report_is_pinned_to_own_branch(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherManager = User::factory()->create([
            'username' => 'mgr'.substr(uniqid(), -6),
            'email' => 'mgr-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $otherBranch->id,
            'is_active' => true,
        ]);

        // Ledger activity exists only in the other branch.
        $this->accountingService->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '42.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '42.00'],
            ],
            'Manual',
            null,
            'Other branch entry',
            now()->toDateString(),
            $otherManager->id,
            $otherBranch->id
        );

        // A forged branch_id is ignored — the manager stays pinned to its
        // own branch and the other branch's posting is excluded.
        $response = $this->actingAs($this->manager)
            ->get('/accounting/trial-balance?branch_id='.$otherBranch->id);

        $response->assertOk();
        $response->assertViewHas('canSelectBranch', false);
        $response->assertViewHas('currentBranch', fn ($branch) => $branch?->id === $this->branch->id);

        /** @var array<int, array{account_code: string, balance: string}> $accounts */
        $accounts = $response->viewData('trialBalance')['accounts'];
        $cash = collect($accounts)->firstWhere('account_code', $this->cashAccount->account_code);
        $this->assertSame(0.0, (float) $cash['balance']);
    }

    #[Test]
    public function cross_branch_report_user_can_select_branch_or_consolidated(): void
    {
        $accountant = User::factory()->create([
            'username' => 'acct'.substr(uniqid(), -6),
            'email' => 'acct-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Accountant,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $otherBranch = Branch::factory()->create();
        $otherManager = User::factory()->create([
            'username' => 'mgr'.substr(uniqid(), -6),
            'email' => 'mgr-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => $otherBranch->id,
            'is_active' => true,
        ]);

        $this->accountingService->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '42.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '42.00'],
            ],
            'Manual',
            null,
            'Other branch entry',
            now()->toDateString(),
            $otherManager->id,
            $otherBranch->id
        );

        // Explicit branch selection scopes the report to that branch.
        $response = $this->actingAs($accountant)
            ->get('/accounting/trial-balance?branch_id='.$otherBranch->id);

        $response->assertOk();
        $response->assertViewHas('canSelectBranch', true);
        $response->assertViewHas('currentBranch', fn ($branch) => $branch?->id === $otherBranch->id);

        /** @var array<int, array{account_code: string, balance: string}> $accounts */
        $accounts = $response->viewData('trialBalance')['accounts'];
        $cash = collect($accounts)->firstWhere('account_code', $this->cashAccount->account_code);
        $this->assertSame(42.0, (float) $cash['balance']);

        // No branch_id means the consolidated all-branch view.
        $response = $this->actingAs($accountant)->get('/accounting/trial-balance');

        $response->assertOk();
        $response->assertViewHas('currentBranch', null);
    }

    #[Test]
    public function unassigned_branch_user_cannot_view_branch_reports(): void
    {
        $orphan = User::factory()->create([
            'username' => 'orphan'.substr(uniqid(), -6),
            'email' => 'orphan-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager,
            'branch_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($orphan)
            ->get('/accounting/trial-balance')
            ->assertForbidden();
    }

    #[Test]
    public function accountant_can_post_an_expense_for_another_branch(): void
    {
        $accountant = User::factory()->create([
            'username' => 'acct'.substr(uniqid(), -6),
            'email' => 'acct-'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Accountant,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $otherBranch = Branch::factory()->create(['petty_cash_float' => 5000]);

        // Cross-branch role: the submitted branch is honoured.
        $response = $this->actingAs($accountant)->post('/accounting/expenses', [
            'branch_id' => $otherBranch->id,
            'account_code' => '6299',
            'category' => 'Operations',
            'description' => 'Cross-branch expense',
            'amount' => '250.00',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', [
            'branch_id' => $otherBranch->id,
            'description' => 'Cross-branch expense',
        ]);
    }

    #[Test]
    public function branch_scoped_expense_post_ignores_submitted_branch(): void
    {
        // A branch-scoped poster cannot smuggle in another branch_id.
        $otherBranch = Branch::factory()->create(['petty_cash_float' => 5000]);
        $this->branch->petty_cash_float = '5000';
        $this->branch->save();

        $response = $this->actingAs($this->manager)->post('/accounting/expenses', [
            'branch_id' => $otherBranch->id,
            'account_code' => '6299',
            'category' => 'Operations',
            'description' => 'Own-branch expense',
            'amount' => '100.00',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', [
            'branch_id' => $this->branch->id,
            'description' => 'Own-branch expense',
        ]);
        $this->assertDatabaseMissing('expenses', [
            'branch_id' => $otherBranch->id,
            'description' => 'Own-branch expense',
        ]);
    }

    #[Test]
    public function it_validates_debits_equal_credits(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/journal', [
                'entry_date' => now()->format('Y-m-d'),
                'description' => 'Imbalanced entry',
                'lines' => [
                    [
                        'account_code' => $this->cashAccount->account_code,
                        'debit' => '1000.00',
                        'credit' => '0.00',
                    ],
                    [
                        'account_code' => $this->revenueAccount->account_code,
                        'debit' => '0.00',
                        'credit' => '500.00', // Not equal!
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    #[Test]
    public function it_creates_journal_entry_posted_directly(): void
    {
        // Journal entries are now posted directly without approval workflow
        $response = $this->actingAs($this->manager)
            ->post('/accounting/journal', [
                'entry_date' => now()->format('Y-m-d'),
                'description' => 'Test entry - should be posted directly',
                'reference_type' => 'Manual',
                'lines' => [
                    [
                        'account_code' => $this->cashAccount->account_code,
                        'debit' => '1000.00',
                        'credit' => '0.00',
                    ],
                    [
                        'account_code' => $this->revenueAccount->account_code,
                        'debit' => '0.00',
                        'credit' => '1000.00',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    #[Test]
    public function it_can_access_trial_balance_endpoint(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/accounting/trial-balance');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_can_access_profit_and_loss_endpoint(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/accounting/profit-loss');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_can_access_balance_sheet_endpoint(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/accounting/balance-sheet');

        $response->assertStatus(200);
    }

    #[Test]
    public function sequential_postings_chain_running_balances_per_account(): void
    {
        $service = app(AccountingService::class);

        $first = $service->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '100.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '100.00'],
            ],
            'Test',
            null,
            'First posting',
            now()->toDateString(),
            $this->manager->id,
            $this->branch->id
        );

        $second = $service->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '250.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '250.00'],
            ],
            'Test',
            null,
            'Second posting',
            now()->toDateString(),
            $this->manager->id,
            $this->branch->id
        );

        $balances = AccountLedger::where('account_code', $this->cashAccount->account_code)
            ->where('branch_id', $this->branch->id)
            ->orderBy('journal_entry_id')
            ->pluck('running_balance')
            ->all();

        // 100 after the first entry, then 350 after the second: the second
        // posting must read the balance written by the first, not a stale 0.
        $this->assertSame(['100.0000', '350.0000'], array_map(
            fn ($v) => bcadd((string) $v, '0', 4),
            $balances
        ));

        $this->assertNotNull($first->id);
        $this->assertNotNull($second->id);
    }

    #[Test]
    public function journal_posting_flushes_ledger_and_reports_cache_tags(): void
    {
        Cache::tags(['ledger', 'trial-balance'])->put('tb_probe', 'stale', 600);
        Cache::tags(['reports', 'cash-flow'])->put('cf_probe', 'stale', 600);

        $service = app(AccountingService::class);
        $service->createJournalEntry(
            [
                ['account_code' => $this->cashAccount->account_code, 'debit' => '50.00', 'credit' => '0.00'],
                ['account_code' => $this->revenueAccount->account_code, 'debit' => '0.00', 'credit' => '50.00'],
            ],
            'Test',
            null,
            'Cache invalidation probe',
            now()->toDateString(),
            $this->manager->id,
            $this->branch->id
        );

        $this->assertNull(Cache::tags(['ledger', 'trial-balance'])->get('tb_probe'));
        $this->assertNull(Cache::tags(['reports', 'cash-flow'])->get('cf_probe'));
    }

    #[Test]
    public function journal_entry_is_created_directly_as_posted_with_ledger_entries(): void
    {
        $response = $this->actingAs($this->manager)
            ->post('/accounting/journal', [
                'entry_date' => now()->format('Y-m-d'),
                'description' => 'Test direct post entry',
                'lines' => [
                    [
                        'account_code' => $this->cashAccount->account_code,
                        'debit' => '1000.00',
                        'credit' => '0.00',
                    ],
                    [
                        'account_code' => $this->revenueAccount->account_code,
                        'debit' => '0.00',
                        'credit' => '1000.00',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $entry = JournalEntry::first();
        $this->assertNotNull($entry);
        $this->assertEquals('Posted', $entry->status->value);
        $this->assertNotNull($entry->posted_at);
        $this->assertNotNull($entry->posted_by);

        $this->assertCount(2, AccountLedger::where('journal_entry_id', $entry->id)->get());
    }

    #[Test]
    public function journal_entry_reversal_marks_original_reversed_and_creates_reversing_entry(): void
    {
        $createResponse = $this->actingAs($this->manager)
            ->post('/accounting/journal', [
                'entry_date' => now()->format('Y-m-d'),
                'description' => 'Entry to be reversed',
                'lines' => [
                    [
                        'account_code' => $this->cashAccount->account_code,
                        'debit' => '500.00',
                        'credit' => '0.00',
                    ],
                    [
                        'account_code' => $this->revenueAccount->account_code,
                        'debit' => '0.00',
                        'credit' => '500.00',
                    ],
                ],
            ]);

        $createResponse->assertSessionHasNoErrors();

        $originalEntry = JournalEntry::first();
        $this->assertEquals('Posted', $originalEntry->status->value);

        $reverseResponse = $this->actingAs($this->manager)
            ->post("/accounting/journal/{$originalEntry->id}/reverse", [
                'reason' => 'Test reversal',
            ]);

        $reverseResponse->assertSessionHasNoErrors();

        $originalEntry->refresh();
        $this->assertEquals('Reversed', $originalEntry->status->value);
        $this->assertNotNull($originalEntry->reversed_at);
        $this->assertNotNull($originalEntry->reversed_by);

        $reversalEntry = JournalEntry::where('reference_id', $originalEntry->id)->first();
        $this->assertNotNull($reversalEntry);
        $this->assertEquals('Posted', $reversalEntry->status->value);

        $originalCashLine = $originalEntry->lines->firstWhere('account_code', $this->cashAccount->account_code);
        $reversalCashLine = $reversalEntry->lines->firstWhere('account_code', $this->cashAccount->account_code);
        $this->assertEquals($originalCashLine->debit, $reversalCashLine->credit);
        $this->assertEquals($originalCashLine->credit, $reversalCashLine->debit);
    }

    #[Test]
    public function closing_entries_use_correct_income_summary_account_type(): void
    {
        // Income Summary (4998) is classified as Equity in the chart of accounts,
        // but should be treated as a debit-normal account when creating closing ledger entries.
        // This ensures that when revenue is credited (reducing it) and expenses are debited (reducing it),
        // the Income Summary account balance correctly reflects the net income/loss.

        $incomeSummaryAccount = ChartOfAccount::factory()->create([
            'account_code' => '4998',
            'account_name' => 'Income Summary',
            'account_type' => 'Equity', // Correctly classified as Equity
            'is_active' => true,
        ]);

        $retainedEarningsAccount = ChartOfAccount::factory()->create([
            'account_code' => '4999',
            'account_name' => 'Retained Earnings',
            'account_type' => 'Equity',
            'is_active' => true,
        ]);

        $expenseAccount = ChartOfAccount::factory()->create([
            'account_code' => '6999',
            'account_name' => 'Test Expenses',
            'account_type' => 'Expense',
            'is_active' => true,
        ]);

        // Create an accounting period for the fiscal year
        $period = AccountingPeriod::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_code' => '2026-01',
            'period_type' => 'month',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'Closed',
        ]);

        // Create journal entry with debit to expense and credit to income summary
        $expenseEntry = JournalEntry::create([
            'entry_number' => 'TEST-2026-001',
            'entry_date' => '2026-06-30',
            'period_id' => $period->id,
            'reference_type' => 'Manual',
            'description' => 'Test expense entry',
            'status' => 'Posted',
            'created_by' => $this->manager->id,
            'posted_by' => $this->manager->id,
            'posted_at' => now(),
        ]);

        JournalLine::create([
            'journal_entry_id' => $expenseEntry->id,
            'account_code' => $expenseAccount->account_code,
            'debit' => '5000.00',
            'credit' => '0.00',
            'description' => 'Test expense',
        ]);

        JournalLine::create([
            'journal_entry_id' => $expenseEntry->id,
            'account_code' => $this->cashAccount->account_code,
            'debit' => '0.00',
            'credit' => '5000.00',
            'description' => 'Cash payment',
        ]);

        // Create ledger entries for the expense entry
        AccountLedger::create([
            'account_code' => $expenseAccount->account_code,
            'entry_date' => '2026-06-30',
            'journal_entry_id' => $expenseEntry->id,
            'debit' => '5000.00',
            'credit' => '0.00',
            'running_balance' => '5000.00',
        ]);

        AccountLedger::create([
            'account_code' => $this->cashAccount->account_code,
            'entry_date' => '2026-06-30',
            'journal_entry_id' => $expenseEntry->id,
            'debit' => '0.00',
            'credit' => '5000.00',
            'running_balance' => '-5000.00',
        ]);

        // Now create the closing entry: Close Expenses to Income Summary
        // This should DEBIT the expense account and CREDIT the income summary
        $closingEntry = JournalEntry::create([
            'entry_number' => 'CE-202606-001',
            'entry_date' => '2026-12-31',
            'period_id' => $period->id,
            'reference_type' => 'FiscalYearClosing',
            'description' => 'Closing Expenses to Income Summary',
            'status' => 'Posted',
            'created_by' => $this->manager->id,
            'posted_by' => $this->manager->id,
            'posted_at' => now(),
        ]);

        JournalLine::create([
            'journal_entry_id' => $closingEntry->id,
            'account_code' => $expenseAccount->account_code,
            'debit' => '0.00',
            'credit' => '5000.00', // Credit expense to close it
            'description' => 'Close Test Expenses',
        ]);

        JournalLine::create([
            'journal_entry_id' => $closingEntry->id,
            'account_code' => '4998',
            'debit' => '5000.00', // Debit income summary
            'credit' => '0.00',
            'description' => 'Income Summary',
        ]);

        // Call the service method to create closing ledger entries
        $fiscalYearService = app(FiscalYearService::class);

        // Use reflection to call the protected method
        $reflection = new \ReflectionMethod($fiscalYearService, 'postClosingToLedger');
        $reflection->setAccessible(true);
        $reflection->invoke($fiscalYearService, $closingEntry);

        // Verify the Income Summary ledger entry was created correctly
        $incomeSummaryLedger = AccountLedger::where('journal_entry_id', $closingEntry->id)
            ->where('account_code', '4998')
            ->first();

        $this->assertNotNull($incomeSummaryLedger, 'Income Summary ledger entry should be created');
        $this->assertEquals('5000.00', bcadd($incomeSummaryLedger->debit, '0', 2));
        $this->assertEquals('0.00', bcadd($incomeSummaryLedger->credit, '0', 2));

        // Verify the expense account ledger entry
        $expenseLedger = AccountLedger::where('journal_entry_id', $closingEntry->id)
            ->where('account_code', $expenseAccount->account_code)
            ->first();

        $this->assertNotNull($expenseLedger, 'Expense ledger entry should be created');
        $this->assertEquals('0.00', bcadd($expenseLedger->debit, '0', 2));
        $this->assertEquals('5000.00', bcadd($expenseLedger->credit, '0', 2));
    }

    #[Test]
    public function closing_entries_use_bcmath_for_large_numbers(): void
    {
        // Test that BCMath is used for absolute value in closing entries.
        // Using a reasonable amount that stays within decimal(18,4) column precision.
        // The key point is using BCMath consistently for precision.
        $largeNetLoss = '123456789.50';

        $incomeSummaryAccount = ChartOfAccount::factory()->create([
            'account_code' => '4998',
            'account_name' => 'Income Summary',
            'account_type' => 'Equity',
            'is_active' => true,
        ]);

        $retainedEarningsAccount = ChartOfAccount::factory()->create([
            'account_code' => '4999',
            'account_name' => 'Retained Earnings',
            'account_type' => 'Equity',
            'is_active' => true,
        ]);

        $expenseAccount = ChartOfAccount::factory()->create([
            'account_code' => '6999',
            'account_name' => 'Test Large Expenses',
            'account_type' => 'Expense',
            'is_active' => true,
        ]);

        $period = AccountingPeriod::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_code' => '2026-01',
            'period_type' => 'month',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'Closed',
        ]);

        // Create journal entry with large expense (net loss)
        $expenseEntry = JournalEntry::create([
            'entry_number' => 'TEST-2026-LARGE',
            'entry_date' => '2026-06-30',
            'period_id' => $period->id,
            'reference_type' => 'Manual',
            'description' => 'Test large expense entry',
            'status' => 'Posted',
            'created_by' => $this->manager->id,
            'posted_by' => $this->manager->id,
            'posted_at' => now(),
        ]);

        JournalLine::create([
            'journal_entry_id' => $expenseEntry->id,
            'account_code' => $expenseAccount->account_code,
            'debit' => $largeNetLoss,
            'credit' => '0.00',
            'description' => 'Test large expense',
        ]);

        JournalLine::create([
            'journal_entry_id' => $expenseEntry->id,
            'account_code' => $this->cashAccount->account_code,
            'debit' => '0.00',
            'credit' => $largeNetLoss,
            'description' => 'Cash payment',
        ]);

        // Create ledger entries
        AccountLedger::create([
            'account_code' => $expenseAccount->account_code,
            'entry_date' => '2026-06-30',
            'journal_entry_id' => $expenseEntry->id,
            'debit' => $largeNetLoss,
            'credit' => '0.00',
            'running_balance' => $largeNetLoss,
        ]);

        AccountLedger::create([
            'account_code' => $this->cashAccount->account_code,
            'entry_date' => '2026-06-30',
            'journal_entry_id' => $expenseEntry->id,
            'debit' => '0.00',
            'credit' => $largeNetLoss,
            'running_balance' => bcsub('0', $largeNetLoss, 2),
        ]);

        // Create closing entry with net loss
        $closingEntry = JournalEntry::create([
            'entry_number' => 'CE-202606-002',
            'entry_date' => '2026-12-31',
            'period_id' => $period->id,
            'reference_type' => 'FiscalYearClosing',
            'description' => 'Closing Expenses to Income Summary (Loss)',
            'status' => 'Posted',
            'created_by' => $this->manager->id,
            'posted_by' => $this->manager->id,
            'posted_at' => now(),
        ]);

        JournalLine::create([
            'journal_entry_id' => $closingEntry->id,
            'account_code' => $expenseAccount->account_code,
            'debit' => '0.00',
            'credit' => $largeNetLoss,
            'description' => 'Close Test Large Expenses',
        ]);

        JournalLine::create([
            'journal_entry_id' => $closingEntry->id,
            'account_code' => '4998',
            'debit' => '0.00',
            'credit' => $largeNetLoss,
            'description' => 'Income Summary (Loss)',
        ]);

        // postClosingToLedger now enforces the balance invariant — complete
        // the entry with the debit legs a real loss close carries.
        JournalLine::create([
            'journal_entry_id' => $closingEntry->id,
            'account_code' => '4998',
            'debit' => $largeNetLoss,
            'credit' => '0.00',
            'description' => 'Income Summary — expenses closed in',
        ]);

        JournalLine::create([
            'journal_entry_id' => $closingEntry->id,
            'account_code' => $retainedEarningsAccount->account_code,
            'debit' => $largeNetLoss,
            'credit' => '0.00',
            'description' => 'Transfer to Retained Earnings (Loss)',
        ]);

        // Call the service method to create closing ledger entries
        $fiscalYearService = app(FiscalYearService::class);

        $reflection = new \ReflectionMethod($fiscalYearService, 'postClosingToLedger');
        $reflection->setAccessible(true);
        $reflection->invoke($fiscalYearService, $closingEntry);

        // Verify the Income Summary ledger entry
        $incomeSummaryLedger = AccountLedger::where('journal_entry_id', $closingEntry->id)
            ->where('account_code', '4998')
            ->first();

        $this->assertNotNull($incomeSummaryLedger, 'Income Summary ledger entry should be created');
        // Verify debit is zero using BCMath comparison
        $this->assertEquals(0, bccomp($incomeSummaryLedger->debit, '0', 4), 'Debit should be 0');
        // Verify credit equals the large net loss using BCMath comparison
        $this->assertEquals(0, bccomp($incomeSummaryLedger->credit, $largeNetLoss, 4), 'Credit should match large net loss');

        // Verify expense account is closed correctly
        $expenseLedger = AccountLedger::where('journal_entry_id', $closingEntry->id)
            ->where('account_code', $expenseAccount->account_code)
            ->first();

        $this->assertNotNull($expenseLedger, 'Expense ledger entry should be created');
        // Verify debit is zero
        $this->assertEquals(0, bccomp($expenseLedger->debit, '0', 4), 'Expense debit should be 0');
        // Verify credit matches large net loss
        $this->assertEquals(0, bccomp($expenseLedger->credit, $largeNetLoss, 4), 'Expense credit should match large net loss');
    }
}
