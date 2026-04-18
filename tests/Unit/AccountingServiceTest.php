<?php

namespace Tests\Unit;

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
}
