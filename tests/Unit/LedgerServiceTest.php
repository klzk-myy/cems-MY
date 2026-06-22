<?php

namespace Tests\Unit;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $service;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(LedgerService::class);
        $this->branch = Branch::factory()->create(['code' => 'HQ', 'name' => 'Headquarters']);

        $this->seedChartOfAccounts();
    }

    protected function seedChartOfAccounts(): void
    {
        $accounts = [
            ['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset'],
            ['account_code' => '2000', 'account_name' => 'Payables', 'account_type' => 'Liability'],
            ['account_code' => '3000', 'account_name' => 'Capital', 'account_type' => 'Equity'],
            ['account_code' => '4000', 'account_name' => 'Sales', 'account_type' => 'Revenue'],
            ['account_code' => '5000', 'account_name' => 'Rent Expense', 'account_type' => 'Expense'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $account['account_code']],
                array_merge($account, ['is_active' => true])
            );
        }
    }

    protected function createPeriod(string $date): AccountingPeriod
    {
        return AccountingPeriod::updateOrCreate(
            ['period_code' => substr($date, 0, 7)],
            [
                'start_date' => $date,
                'end_date' => $date,
                'status' => 'Open',
            ]
        );
    }

    protected function createLedgerEntry(string $accountCode, string $date, string $debit, string $credit, string $balance): void
    {
        $period = $this->createPeriod($date);
        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => $date,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        DB::table('account_ledger')->insert([
            'account_code' => $accountCode,
            'branch_id' => $this->branch->id,
            'entry_date' => $date,
            'journal_entry_id' => $journalEntry->id,
            'debit' => $debit,
            'credit' => $credit,
            'running_balance' => $balance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function get_trial_balance_returns_all_accounts(): void
    {
        $result = $this->service->getTrialBalance(now()->toDateString());

        $this->assertArrayHasKey('accounts', $result);
        $this->assertArrayHasKey('total_debits', $result);
        $this->assertArrayHasKey('total_credits', $result);
        $this->assertArrayHasKey('is_balanced', $result);

        $codes = collect($result['accounts'])->pluck('account_code')->toArray();
        $this->assertContains('1000', $codes);
        $this->assertContains('4000', $codes);
        $this->assertContains('5000', $codes);
    }

    #[Test]
    public function get_trial_balance_has_debit_and_credit_columns(): void
    {
        $this->createLedgerEntry('1000', now()->toDateString(), '1000.00', '0.00', '1000.00');

        $result = $this->service->getTrialBalance(now()->toDateString());

        $cash = collect($result['accounts'])->firstWhere('account_code', '1000');
        $this->assertNotNull($cash);
        $this->assertArrayHasKey('debit', $cash);
        $this->assertArrayHasKey('credit', $cash);
        $this->assertArrayHasKey('balance', $cash);
    }

    #[Test]
    public function get_account_ledger_returns_entries(): void
    {
        $today = now()->toDateString();
        $this->createLedgerEntry('1000', $today, '500.00', '0.00', '500.00');
        $this->createLedgerEntry('1000', $today, '300.00', '0.00', '800.00');

        $result = $this->service->getAccountLedger('1000', $today, $today);

        $this->assertArrayHasKey('account', $result);
        $this->assertArrayHasKey('entries', $result);
        $this->assertArrayHasKey('opening_balance', $result);
        $this->assertArrayHasKey('closing_balance', $result);
        $this->assertCount(2, $result['entries']);
        $this->assertEquals('800.0000', $result['closing_balance']);
    }

    #[Test]
    public function get_profit_and_loss_returns_revenue_and_expenses(): void
    {
        $today = now()->toDateString();
        $this->createLedgerEntry('4000', $today, '0.00', '5000.00', '5000.00');
        $this->createLedgerEntry('5000', $today, '2000.00', '0.00', '2000.00');

        $result = $this->service->getProfitAndLoss($today, $today);

        $this->assertArrayHasKey('revenues', $result);
        $this->assertArrayHasKey('expenses', $result);
        $this->assertArrayHasKey('net_profit', $result);

        $revenueCodes = collect($result['revenues'])->pluck('account_code')->toArray();
        $expenseCodes = collect($result['expenses'])->pluck('account_code')->toArray();

        $this->assertContains('4000', $revenueCodes);
        $this->assertContains('5000', $expenseCodes);
    }

    #[Test]
    public function get_balance_sheet_returns_assets_liabilities_equity(): void
    {
        $today = now()->toDateString();
        $this->createLedgerEntry('1000', $today, '10000.00', '0.00', '10000.00');
        $this->createLedgerEntry('2000', $today, '0.00', '3000.00', '3000.00');
        $this->createLedgerEntry('3000', $today, '0.00', '7000.00', '7000.00');

        $result = $this->service->getBalanceSheet($today);

        $this->assertArrayHasKey('assets', $result);
        $this->assertArrayHasKey('liabilities', $result);
        $this->assertArrayHasKey('equity', $result);
        $this->assertArrayHasKey('is_balanced', $result);

        $assetCodes = collect($result['assets'])->pluck('account_code')->toArray();
        $liabilityCodes = collect($result['liabilities'])->pluck('account_code')->toArray();
        $equityCodes = collect($result['equity'])->pluck('account_code')->toArray();

        $this->assertContains('1000', $assetCodes);
        $this->assertContains('2000', $liabilityCodes);
        $this->assertContains('3000', $equityCodes);
    }

    #[Test]
    public function profit_and_loss_calculates_net_profit(): void
    {
        $today = now()->toDateString();
        $this->createLedgerEntry('4000', $today, '0.00', '10000.00', '10000.00');
        $this->createLedgerEntry('5000', $today, '6000.00', '0.00', '6000.00');

        $result = $this->service->getProfitAndLoss($today, $today);

        $this->assertEquals('10000.0000', $result['total_revenue']);
        $this->assertEquals('6000.0000', $result['total_expenses']);
        $this->assertEquals('4000.0000', $result['net_profit']);
    }

    #[Test]
    public function balance_sheet_assets_equal_liabilities_plus_equity(): void
    {
        $today = now()->toDateString();
        $this->createLedgerEntry('1000', $today, '10000.00', '0.00', '10000.00');
        $this->createLedgerEntry('2000', $today, '0.00', '3000.00', '3000.00');
        $this->createLedgerEntry('3000', $today, '0.00', '7000.00', '7000.00');

        $result = $this->service->getBalanceSheet($today);

        $this->assertTrue($result['is_balanced']);
        $this->assertEquals(
            $result['total_assets'],
            $result['liabilities_plus_equity']
        );
    }

    #[Test]
    public function get_account_balance_respects_branch_filter(): void
    {
        $today = now()->toDateString();
        $branch2 = Branch::factory()->create(['code' => 'B2', 'name' => 'Branch 2']);

        $this->createLedgerEntry('1000', $today, '1000.00', '0.00', '1000.00');

        $period = $this->createPeriod($today);
        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => $today,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        DB::table('account_ledger')->insert([
            'account_code' => '1000',
            'branch_id' => $branch2->id,
            'entry_date' => $today,
            'journal_entry_id' => $journalEntry->id,
            'debit' => '500.00',
            'credit' => '0.00',
            'running_balance' => '500.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultAll = $this->service->getAccountBalancesForPeriod($today, $today);
        $resultBranch2 = $this->service->getAccountBalancesForPeriod($today, $today, $branch2->id);

        $allCash = collect($resultAll['accounts'])->firstWhere('account_code', '1000');
        $branch2Cash = collect($resultBranch2['accounts'])->firstWhere('account_code', '1000');

        $this->assertNotNull($allCash);
        $this->assertEquals('1500', $allCash['debit']);

        $this->assertNotNull($branch2Cash);
        $this->assertEquals('500', $branch2Cash['debit']);
    }

    #[Test]
    public function profit_loss_by_branch(): void
    {
        $today = now()->toDateString();
        $branch2 = Branch::factory()->create(['code' => 'B2', 'name' => 'Branch 2']);

        $this->createLedgerEntry('4000', $today, '0.00', '5000.00', '5000.00');

        $period = $this->createPeriod($today);
        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => $today,
            'period_id' => $period->id,
            'status' => JournalEntryStatus::Posted,
        ]);

        DB::table('account_ledger')->insert([
            'account_code' => '4000',
            'branch_id' => $branch2->id,
            'entry_date' => $today,
            'journal_entry_id' => $journalEntry->id,
            'debit' => '0.00',
            'credit' => '3000.00',
            'running_balance' => '3000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultAll = $this->service->getProfitAndLoss($today, $today);
        $resultBranch2 = $this->service->getProfitAndLoss($today, $today, $branch2->id);

        $this->assertEquals('8000.0000', $resultAll['total_revenue']);
        $this->assertEquals('3000.0000', $resultBranch2['total_revenue']);
    }

    #[Test]
    public function get_trial_balance_executes_under_20_queries(): void
    {
        $today = now()->toDateString();
        // Create some ledger entries
        $this->createLedgerEntry('1000', $today, '1000.00', '0.00', '1000.00');
        $this->createLedgerEntry('2000', $today, '0.00', '500.00', '500.00');

        DB::enableQueryLog();
        $this->service->getTrialBalance($today);
        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(20, $queryCount, "Expected <= 20 queries for getTrialBalance, got $queryCount");
    }

    #[Test]
    public function get_profit_and_loss_executes_under_20_queries(): void
    {
        $today = now()->toDateString();
        // Create ledger entries for revenue and expense accounts
        $this->createLedgerEntry('4000', $today, '0.00', '5000.00', '5000.00'); // Revenue
        $this->createLedgerEntry('5000', $today, '2000.00', '0.00', '2000.00'); // Expense

        DB::enableQueryLog();
        $this->service->getProfitAndLoss($today, $today);
        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(20, $queryCount, "Expected <= 20 queries for getProfitAndLoss, got $queryCount");
    }

    #[Test]
    public function get_balance_sheet_executes_under_20_queries(): void
    {
        $today = now()->toDateString();
        // Create ledger entries for asset, liability, equity
        $this->createLedgerEntry('1000', $today, '10000.00', '0.00', '10000.00'); // Asset
        $this->createLedgerEntry('2000', $today, '0.00', '3000.00', '3000.00'); // Liability
        $this->createLedgerEntry('3000', $today, '0.00', '7000.00', '7000.00'); // Equity

        DB::enableQueryLog();
        $this->service->getBalanceSheet($today);
        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(20, $queryCount, "Expected <= 20 queries for getBalanceSheet, got $queryCount");
    }
}
