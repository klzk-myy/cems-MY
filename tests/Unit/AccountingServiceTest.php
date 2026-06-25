<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\AuditService;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
    }

    #[Test]
    public function journal_entry_must_be_balanced(): void
    {
        $service = new AccountingService($this->mathService, new AuditService);

        $result = $service->validateBalanced([
            ['debit' => '1000.00', 'credit' => '0'],
            ['debit' => '0', 'credit' => '1000.00'],
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function unbalanced_entry_rejected(): void
    {
        $service = new AccountingService($this->mathService, new AuditService);

        $result = $service->validateBalanced([
            ['debit' => '1000.00', 'credit' => '0'],
            ['debit' => '0', 'credit' => '999.00'],
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function validate_balanced_returns_true_for_balanced_entry(): void
    {
        $service = new AccountingService($this->mathService, new AuditService);

        $result = $service->validateBalanced([
            ['debit' => '1000.00', 'credit' => '0'],
            ['debit' => '500.00', 'credit' => '0'],
            ['debit' => '0', 'credit' => '1500.00'],
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function validate_balanced_returns_false_for_unbalanced_entry(): void
    {
        $service = new AccountingService($this->mathService, new AuditService);

        $result = $service->validateBalanced([
            ['debit' => '1000.00', 'credit' => '0'],
            ['debit' => '0', 'credit' => '500.00'],
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_reverse_journal_entry(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '1010',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '4010',
            'account_name' => 'Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $entry = $service->createJournalEntry(
            [
                ['account_code' => '1010', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '4010', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Manual',
            null,
            'Original entry',
            '2026-01-15',
            $user->id
        );

        $reversal = $service->reverseJournalEntry($entry, 'Test reversal', $user->id);

        $this->assertNotNull($reversal);
        $this->assertEquals('Reversal', $reversal->reference_type->value);
    }

    #[Test]
    public function reversal_creates_explicit_link(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '1020',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $expenseAccount = ChartOfAccount::factory()->create([
            'account_code' => '5010',
            'account_name' => 'Expense',
            'account_type' => 'Expense',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $original = $service->createJournalEntry(
            [
                ['account_code' => '5010', 'debit' => '500.00', 'credit' => '0'],
                ['account_code' => '1020', 'debit' => '0', 'credit' => '500.00'],
            ],
            'Manual',
            null,
            'Expense entry',
            '2026-01-15',
            $user->id
        );

        $reversal = $service->reverseJournalEntry($original, 'Link test', $user->id);

        $this->assertEquals($original->id, $reversal->reference_id);
    }

    #[Test]
    public function reversed_entry_status_is_updated(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '1030',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '4020',
            'account_name' => 'Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $entry = $service->createJournalEntry(
            [
                ['account_code' => '1030', 'debit' => '750.00', 'credit' => '0'],
                ['account_code' => '4020', 'debit' => '0', 'credit' => '750.00'],
            ],
            'Manual',
            null,
            'Status test entry',
            '2026-01-15',
            $user->id
        );

        $service->reverseJournalEntry($entry, 'Status check', $user->id);

        $entry->refresh();
        $this->assertEquals('Reversed', $entry->status->value);
    }

    #[Test]
    public function get_account_balance_returns_correct_balance(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '1040',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '4030',
            'account_name' => 'Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $service->createJournalEntry(
            [
                ['account_code' => '1040', 'debit' => '5000.00', 'credit' => '0'],
                ['account_code' => '4030', 'debit' => '0', 'credit' => '5000.00'],
            ],
            'Manual',
            null,
            'Balance test entry',
            '2026-01-15',
            $user->id
        );

        $balance = $service->getAccountBalance('1040', '2026-01-15');
        $this->assertEquals('5000.0000', $balance);
    }

    #[Test]
    public function get_account_balance_returns_zero_for_no_entries(): void
    {
        $service = new AccountingService($this->mathService, new AuditService);

        $balance = $service->getAccountBalance('9999');
        $this->assertEquals('0', $balance);
    }

    #[Test]
    public function debit_account_balance_increases_with_debit_and_decreases_with_credit(): void
    {
        $assetAccount = ChartOfAccount::factory()->create([
            'account_code' => '1050',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $liabilityAccount = ChartOfAccount::factory()->create([
            'account_code' => '2010',
            'account_name' => 'Payable',
            'account_type' => 'Liability',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $service->createJournalEntry(
            [
                ['account_code' => '1050', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '2010', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Manual',
            null,
            'Initial balance',
            '2026-01-10',
            $user->id
        );

        $service->createJournalEntry(
            [
                ['account_code' => '1050', 'debit' => '500.00', 'credit' => '0'],
                ['account_code' => '2010', 'debit' => '0', 'credit' => '500.00'],
            ],
            'Manual',
            null,
            'Debit increases asset',
            '2026-01-15',
            $user->id
        );

        $service->createJournalEntry(
            [
                ['account_code' => '2010', 'debit' => '200.00', 'credit' => '0'],
                ['account_code' => '1050', 'debit' => '0', 'credit' => '200.00'],
            ],
            'Manual',
            null,
            'Credit decreases asset',
            '2026-01-20',
            $user->id
        );

        $balance = $service->getAccountBalance('1050', '2026-01-20');
        $this->assertEquals('1300.0000', $balance);
    }

    #[Test]
    public function credit_account_balance_decreases_with_debit_and_increases_with_credit(): void
    {
        $assetAccount = ChartOfAccount::factory()->create([
            'account_code' => '1060',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $liabilityAccount = ChartOfAccount::factory()->create([
            'account_code' => '2020',
            'account_name' => 'Payable',
            'account_type' => 'Liability',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $service->createJournalEntry(
            [
                ['account_code' => '1060', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '2020', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Manual',
            null,
            'Initial liability',
            '2026-01-10',
            $user->id
        );

        $service->createJournalEntry(
            [
                ['account_code' => '1060', 'debit' => '0', 'credit' => '500.00'],
                ['account_code' => '2020', 'debit' => '500.00', 'credit' => '0'],
            ],
            'Manual',
            null,
            'Debit decreases liability',
            '2026-01-15',
            $user->id
        );

        $service->createJournalEntry(
            [
                ['account_code' => '1060', 'debit' => '500.00', 'credit' => '0'],
                ['account_code' => '2020', 'debit' => '0', 'credit' => '500.00'],
            ],
            'Manual',
            null,
            'Credit increases liability',
            '2026-01-20',
            $user->id
        );

        $balance = $service->getAccountBalance('2020', '2026-01-20');
        $this->assertEquals('1000.0000', $balance);
    }

    #[Test]
    public function revenue_account_balance_increases_with_credit(): void
    {
        $assetAccount = ChartOfAccount::factory()->create([
            'account_code' => '1070',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '4040',
            'account_name' => 'Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $service->createJournalEntry(
            [
                ['account_code' => '1070', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '4040', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Manual',
            null,
            'Revenue recognition',
            '2026-01-15',
            $user->id
        );

        $balance = $service->getAccountBalance('4040', '2026-01-15');
        $this->assertEquals('1000.0000', $balance);
    }

    #[Test]
    public function expense_account_balance_increases_with_debit(): void
    {
        $assetAccount = ChartOfAccount::factory()->create([
            'account_code' => '1080',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $expenseAccount = ChartOfAccount::factory()->create([
            'account_code' => '5020',
            'account_name' => 'Rent Expense',
            'account_type' => 'Expense',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $service->createJournalEntry(
            [
                ['account_code' => '5020', 'debit' => '500.00', 'credit' => '0'],
                ['account_code' => '1080', 'debit' => '0', 'credit' => '500.00'],
            ],
            'Manual',
            null,
            'Rent payment',
            '2026-01-15',
            $user->id
        );

        $balance = $service->getAccountBalance('5020', '2026-01-15');
        $this->assertEquals('500.0000', $balance);
    }

    #[Test]
    public function comprehensive_balance_calculation(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '1090',
            'account_name' => 'Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '4050',
            'account_name' => 'Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        FiscalYear::factory()->create(['year_code' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        AccountingPeriod::factory()->create([
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();
        $service = new AccountingService($this->mathService, new AuditService);

        $service->createJournalEntry(
            [
                ['account_code' => '1090', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '4050', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Manual', null, 'Entry 1', '2026-01-10', $user->id
        );

        $service->createJournalEntry(
            [
                ['account_code' => '1090', 'debit' => '2000.00', 'credit' => '0'],
                ['account_code' => '4050', 'debit' => '0', 'credit' => '2000.00'],
            ],
            'Manual', null, 'Entry 2', '2026-01-15', $user->id
        );

        $service->createJournalEntry(
            [
                ['account_code' => '1090', 'debit' => '500.00', 'credit' => '0'],
                ['account_code' => '4050', 'debit' => '0', 'credit' => '500.00'],
            ],
            'Manual', null, 'Entry 3', '2026-01-20', $user->id
        );

        $balance = $service->getAccountBalance('1090', '2026-01-20');
        $this->assertEquals('3500.0000', $balance);

        $revenueBalance = $service->getAccountBalance('4050', '2026-01-20');
        $this->assertEquals('3500.0000', $revenueBalance);
    }

    #[Test]
    public function balance_calculation_with_zero_amounts(): void
    {
        $service = new AccountingService($this->mathService, new AuditService);

        $result = $service->validateBalanced([
            ['debit' => '0.00', 'credit' => '0.00'],
            ['debit' => '0.00', 'credit' => '0.00'],
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function update_ledger_is_atomic(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '9999',
            'account_name' => 'Test Cash',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $revenueAccount = ChartOfAccount::factory()->create([
            'account_code' => '5999',
            'account_name' => 'Test Revenue',
            'account_type' => 'Revenue',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $auditService = new AuditService;
        $accountingService = new AccountingService($this->mathService, $auditService);

        $initialCount = AccountLedger::count();

        $entry = $accountingService->createJournalEntry(
            [
                ['account_code' => '9999', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '5999', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Test',
            null,
            'Atomic test entry'
        );

        $newCount = AccountLedger::count();
        $expectedNewEntries = 2;

        $this->assertEquals($initialCount + $expectedNewEntries, $newCount);

        $cashLedger = AccountLedger::where('account_code', '9999')
            ->where('journal_entry_id', $entry->id)
            ->first();
        $this->assertNotNull($cashLedger);
        $this->assertEquals('1000.0000', $cashLedger->debit);
        $this->assertEquals('0.0000', $cashLedger->credit);

        $revenueLedger = AccountLedger::where('account_code', '5999')
            ->where('journal_entry_id', $entry->id)
            ->first();
        $this->assertNotNull($revenueLedger);
        $this->assertEquals('0.0000', $revenueLedger->debit);
        $this->assertEquals('1000.0000', $revenueLedger->credit);
    }

    #[Test]
    public function journal_reversal_produces_correct_economic_effect(): void
    {
        $cashAccount = ChartOfAccount::factory()->create([
            'account_code' => '1001',
            'account_name' => 'Cash MYR',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $inventoryAccount = ChartOfAccount::factory()->create([
            'account_code' => '1501',
            'account_name' => 'Foreign Currency Inventory',
            'account_type' => 'Asset',
            'is_active' => true,
            'allow_journal' => true,
        ]);

        $fiscalYear = FiscalYear::factory()->create([
            'year_code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'Open',
        ]);

        $period = AccountingPeriod::factory()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'period_code' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'Open',
        ]);

        $user = User::factory()->create();

        $auditService = new AuditService;
        $accountingService = new AccountingService($this->mathService, $auditService);

        $sellEntry = $accountingService->createJournalEntry(
            [
                ['account_code' => '1001', 'debit' => '5000.00', 'credit' => '0'],
                ['account_code' => '1501', 'debit' => '0', 'credit' => '5000.00'],
            ],
            'Transaction',
            null,
            'SELL transaction - Cash received, Inventory reduced',
            '2026-01-15',
            $user->id
        );

        $cashBalanceAfterSell = $accountingService->getAccountBalance('1001', '2026-01-15');
        $inventoryBalanceAfterSell = $accountingService->getAccountBalance('1501', '2026-01-15');

        $this->assertEquals('5000.0000', $cashBalanceAfterSell);
        $this->assertEquals('-5000.0000', $inventoryBalanceAfterSell);

        $reversalEntry = $accountingService->reverseJournalEntry(
            $sellEntry,
            'Reversal of SELL transaction',
            $user->id
        );

        $reversalCashLine = $reversalEntry->lines->firstWhere('account_code', '1001');
        $reversalInventoryLine = $reversalEntry->lines->firstWhere('account_code', '1501');

        $this->assertEquals('0.0000', $reversalCashLine->debit);
        $this->assertEquals('5000.0000', $reversalCashLine->credit);

        $this->assertEquals('5000.0000', $reversalInventoryLine->debit);
        $this->assertEquals('0.0000', $reversalInventoryLine->credit);

        $cashBalanceAfterReversal = $accountingService->getAccountBalance('1001');
        $inventoryBalanceAfterReversal = $accountingService->getAccountBalance('1501');

        $this->assertEquals('0.0000', $cashBalanceAfterReversal);
        $this->assertEquals('0.0000', $inventoryBalanceAfterReversal);

        $sellEntry->refresh();
        $this->assertEquals('Reversed', $sellEntry->status->value);
        $this->assertNotNull($sellEntry->reversed_at);
    }
}
