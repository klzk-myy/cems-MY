<?php

namespace Tests\Feature;

use App\Enums\BankReconciliationStatus;
use App\Enums\UserRole;
use App\Models\BankReconciliation;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Accounting period-close actions: budget updates, fiscal-year close (with
 * its confirm-code step-up), and the bank reconciliation workflow (import,
 * manual match, exception).
 */
class AccountingCloseActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create(['role' => UserRole::Accountant]);
    }

    #[Test]
    public function budget_update_changes_the_budgeted_amount(): void
    {
        $budget = Budget::factory()->create(['budget_myr' => '5000.00']);

        $this->actingAs($this->accountant)
            ->patch(route('accounting.budget.update', $budget), ['budget_myr' => '7500.50'])
            ->assertRedirect(route('accounting.budget'))
            ->assertSessionHas('success', 'Budget updated successfully.');

        $budget->refresh();
        $this->assertSame('7500.5000', (string) $budget->budget_myr);
    }

    #[Test]
    public function budget_update_rejects_a_negative_amount(): void
    {
        $budget = Budget::factory()->create(['budget_myr' => '5000.00']);

        $this->actingAs($this->accountant)
            ->patch(route('accounting.budget.update', $budget), ['budget_myr' => '-1'])
            ->assertRedirect()
            ->assertSessionHasErrors('budget_myr');

        $budget->refresh();
        $this->assertSame('5000.0000', (string) $budget->budget_myr);
    }

    #[Test]
    public function fiscal_year_close_requires_the_year_code_confirmation(): void
    {
        $year = FiscalYear::create([
            'year_code' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $this->actingAs($this->accountant)
            ->post(route('accounting.fiscal-years.close', $year), ['confirm_code' => 'WRONG-CODE'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Year code confirmation failed.');

        $this->assertFalse($year->refresh()->isClosed(), 'A wrong confirm code must not close the year');
    }

    #[Test]
    public function fiscal_year_close_with_all_periods_closed_succeeds(): void
    {
        $year = FiscalYear::create([
            'year_code' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $this->actingAs($this->accountant)
            ->post(route('accounting.fiscal-years.close', $year), ['confirm_code' => '2025'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($year->refresh()->isClosed(), 'The fiscal year must be closed');
    }

    #[Test]
    public function teller_cannot_close_a_fiscal_year(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);
        $year = FiscalYear::create([
            'year_code' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $this->actingAs($teller)
            ->post(route('accounting.fiscal-years.close', $year), ['confirm_code' => '2025'])
            ->assertForbidden();
    }

    #[Test]
    public function bank_statement_import_creates_reconciliation_lines_and_skips_duplicates(): void
    {
        $account = ChartOfAccount::factory()->create(['account_code' => '1001-TEST']);
        $line = [
            'date' => now()->toDateString(),
            'reference' => 'DEP-001',
            'description' => 'Cash deposit',
            'debit' => '1000.00',
            'credit' => null,
        ];

        // First import creates the line; the second is a duplicate and is skipped.
        $this->actingAs($this->accountant)
            ->post(route('accounting.reconciliation.import'), [
                'account_code' => $account->account_code,
                'lines' => [$line],
            ])
            ->assertRedirect(route('accounting.reconciliation'))
            ->assertSessionHas('success');

        $this->assertSame(1, BankReconciliation::where('account_code', $account->account_code)->count());

        $this->actingAs($this->accountant)
            ->post(route('accounting.reconciliation.import'), [
                'account_code' => $account->account_code,
                'lines' => [$line],
            ])
            ->assertRedirect(route('accounting.reconciliation'));

        $this->assertSame(
            1,
            BankReconciliation::where('account_code', $account->account_code)->count(),
            'Re-importing the same statement line must not duplicate it'
        );
    }

    #[Test]
    public function manual_match_links_an_unmatched_item_to_a_journal_entry(): void
    {
        $account = ChartOfAccount::factory()->create(['account_code' => '1002-TEST']);
        $item = BankReconciliation::factory()->create([
            'account_code' => $account->account_code,
            'status' => 'unmatched',
            'matched_to_journal_entry_id' => null,
        ]);
        $entry = JournalEntry::factory()->create();

        $this->actingAs($this->accountant)
            ->post(route('accounting.reconciliation.match', $item), ['journal_entry_id' => $entry->id])
            ->assertRedirect(route('accounting.reconciliation'))
            ->assertSessionHas('success', 'Item matched to journal entry.');

        $item->refresh();
        $this->assertSame(BankReconciliationStatus::Matched, $item->status);
        $this->assertSame($entry->id, $item->matched_to_journal_entry_id);
    }

    #[Test]
    public function an_unmatched_item_can_be_marked_as_an_exception_with_a_reason(): void
    {
        $account = ChartOfAccount::factory()->create(['account_code' => '1003-TEST']);
        $item = BankReconciliation::factory()->create([
            'account_code' => $account->account_code,
            'status' => 'unmatched',
        ]);

        $this->actingAs($this->accountant)
            ->post(route('accounting.reconciliation.exception', $item), ['reason' => 'Bank fee not booked in GL'])
            ->assertRedirect(route('accounting.reconciliation'))
            ->assertSessionHas('success', 'Item marked as exception.');

        $this->assertSame(BankReconciliationStatus::Exception, $item->refresh()->status);
    }
}
