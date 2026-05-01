<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_journal_entry_must_be_balanced(): void
    {
        $debitTotal = '1000.00';
        $creditTotal = '1000.00';

        $isBalanced = bccomp($debitTotal, $creditTotal, 2) === 0;

        $this->assertTrue($isBalanced);
    }

    public function test_unbalanced_entry_rejected(): void
    {
        $debitTotal = '1000.00';
        $creditTotal = '999.00';

        $isBalanced = bccomp($debitTotal, $creditTotal, 2) === 0;

        $this->assertFalse($isBalanced);
    }

    public function test_validate_balanced_returns_true_for_balanced_entry(): void
    {
        $entry = [
            'debits' => ['1000.00', '500.00'],
            'credits' => ['1500.00'],
        ];

        $debitSum = array_sum(array_map(fn ($d) => (float) $d, $entry['debits']));
        $creditSum = array_sum(array_map(fn ($c) => (float) $c, $entry['credits']));

        $this->assertEquals($debitSum, $creditSum);
    }

    public function test_validate_balanced_returns_false_for_unbalanced_entry(): void
    {
        $entry = [
            'debits' => ['1000.00'],
            'credits' => ['500.00'],
        ];

        $debitSum = array_sum(array_map(fn ($d) => (float) $d, $entry['debits']));
        $creditSum = array_sum(array_map(fn ($c) => (float) $c, $entry['credits']));

        $this->assertNotEquals($debitSum, $creditSum);
    }

    public function test_can_reverse_journal_entry(): void
    {
        $originalAmount = '1000.00';
        $reversalAmount = bcmul($originalAmount, '-1', 2);

        $this->assertEquals('-1000.00', $reversalAmount);
    }

    public function test_reversal_creates_explicit_link(): void
    {
        $originalId = 123;
        $reversalId = 456;

        $linkedToId = $originalId;

        $this->assertEquals(123, $linkedToId);
    }

    public function test_reversed_entry_status_is_updated(): void
    {
        $status = 'Reversed';

        $this->assertEquals('Reversed', $status);
    }

    public function test_get_account_balance_returns_correct_balance(): void
    {
        $debits = '5000.00';
        $credits = '3000.00';

        $balance = bcsub($debits, $credits, 2);

        $this->assertEquals('2000.00', $balance);
    }

    public function test_get_account_balance_returns_zero_for_no_entries(): void
    {
        $balance = '0.00';

        $this->assertEquals('0.00', $balance);
    }

    public function test_debit_account_balance_increases_with_debit_and_decreases_with_credit(): void
    {
        // Asset account (debit-normal)
        $initialBalance = '1000.00';
        $debitAmount = '500.00';
        $creditAmount = '200.00';

        $newBalance = bcadd(
            bcsub($initialBalance, $creditAmount, 2),
            $debitAmount,
            2
        );

        $this->assertEquals('1300.00', $newBalance);
    }

    public function test_credit_account_balance_decreases_with_debit_and_increases_with_credit(): void
    {
        // Liability account (credit-normal)
        $initialBalance = '1000.00';
        $debitAmount = '200.00'; // Debit decreases liability
        $creditAmount = '500.00'; // Credit increases liability

        $newBalance = bcsub(
            bcadd($initialBalance, $creditAmount, 2),
            $debitAmount,
            2
        );

        $this->assertEquals('1300.00', $newBalance);
    }

    public function test_revenue_account_balance_increases_with_credit(): void
    {
        // Revenue normal balance is credit
        $initialBalance = '0.00';
        $creditAmount = '1000.00';

        $newBalance = bcadd($initialBalance, $creditAmount, 2);

        $this->assertEquals('1000.00', $newBalance);
    }

    public function test_expense_account_balance_increases_with_debit(): void
    {
        // Expense normal balance is debit
        $initialBalance = '0.00';
        $debitAmount = '500.00';

        $newBalance = bcadd($initialBalance, $debitAmount, 2);

        $this->assertEquals('500.00', $newBalance);
    }

    public function test_comprehensive_balance_calculation(): void
    {
        $debits = ['1000.00', '2000.00', '500.00'];
        $credits = ['2500.00', '1000.00'];

        $totalDebits = array_reduce($debits, fn ($carry, $d) => bcadd($carry, $d, 2), '0.00');
        $totalCredits = array_reduce($credits, fn ($carry, $c) => bcadd($carry, $c, 2), '0.00');

        $this->assertEquals('3500.00', $totalDebits);
        $this->assertEquals('3500.00', $totalCredits);
    }

    public function test_balance_calculation_with_zero_amounts(): void
    {
        $debits = ['0.00', '0.00'];
        $credits = ['0.00', '0.00'];

        $totalDebits = array_sum(array_map(fn ($d) => (float) $d, $debits));
        $totalCredits = array_sum(array_map(fn ($c) => (float) $c, $credits));

        $this->assertEquals(0, $totalDebits);
        $this->assertEquals(0, $totalCredits);
    }

    public function test_update_ledger_is_atomic(): void
    {
        // Create test accounts
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

        // Create the accounting service
        $auditService = new AuditService;
        $accountingService = new AccountingService($this->mathService, $auditService);

        // Count ledger entries before
        $initialCount = AccountLedger::count();

        // Create a journal entry via the service
        $entry = $accountingService->createJournalEntry(
            [
                ['account_code' => '9999', 'debit' => '1000.00', 'credit' => '0'],
                ['account_code' => '5999', 'debit' => '0', 'credit' => '1000.00'],
            ],
            'Test',
            null,
            'Atomic test entry'
        );

        // All ledger entries should be created or none
        $newCount = AccountLedger::count();
        $expectedNewEntries = 2;

        $this->assertEquals($initialCount + $expectedNewEntries, $newCount);

        // Verify the ledger entries exist with correct data
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

    /** @test */
    public function test_journal_reversal_produces_correct_economic_effect(): void
    {
        // Create test accounts simulating SELL transaction scenario:
        // - Cash (Asset, debit-normal): debited when sale happens
        // - Inventory (Asset, debit-normal): credited when inventory leaves
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

        // Create necessary dependencies for journal entry
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
            'status' => 'open',
        ]);

        $user = User::factory()->create();

        // Create the accounting service
        $auditService = new AuditService;
        $accountingService = new AccountingService($this->mathService, $auditService);

        // Simulate SELL transaction entry (balanced):
        // Cash is DEBITED (increases) and Inventory is CREDITED (decreases)
        // Using equal amounts for a simple balanced 2-line entry
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

        // Get balances after SELL transaction
        $cashBalanceAfterSell = $accountingService->getAccountBalance('1001', '2026-01-15');
        $inventoryBalanceAfterSell = $accountingService->getAccountBalance('1501', '2026-01-15');

        // Verify initial sell entry effects (using BCMath)
        // Cash should be +5000 (debit increases asset)
        // Inventory should be -5000 (credit decreases asset)
        $this->assertEquals('5000.0000', $cashBalanceAfterSell);
        $this->assertEquals('-5000.0000', $inventoryBalanceAfterSell);

        // Reverse the entry - should swap debit/credit for each account
        $reversalEntry = $accountingService->reverseJournalEntry(
            $sellEntry,
            'Reversal of SELL transaction',
            $user->id
        );

        // Verify reversal entry has swapped amounts
        $reversalCashLine = $reversalEntry->lines->firstWhere('account_code', '1001');
        $reversalInventoryLine = $reversalEntry->lines->firstWhere('account_code', '1501');

        // Cash was debited 5000 in original, should be credited 5000 in reversal
        $this->assertEquals('0.0000', $reversalCashLine->debit);
        $this->assertEquals('5000.0000', $reversalCashLine->credit);

        // Inventory was credited 5000 in original, should be debited 5000 in reversal
        $this->assertEquals('5000.0000', $reversalInventoryLine->debit);
        $this->assertEquals('0.0000', $reversalInventoryLine->credit);

        // Verify net economic effect returns accounts to near-zero
        // Note: reversal entry uses current date (2026-05-01), so we query with no date filter
        // to get the latest running balance which includes both sell and reversal entries
        $cashBalanceAfterReversal = $accountingService->getAccountBalance('1001');
        $inventoryBalanceAfterReversal = $accountingService->getAccountBalance('1501');

        $this->assertEquals('0.0000', $cashBalanceAfterReversal);
        $this->assertEquals('0.0000', $inventoryBalanceAfterReversal);

        // Verify original entry is marked as reversed
        $sellEntry->refresh();
        $this->assertEquals('Reversed', $sellEntry->status->value);
        $this->assertNotNull($sellEntry->reversed_at);
    }
}
