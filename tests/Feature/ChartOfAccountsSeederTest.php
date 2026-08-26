<?php

namespace Tests\Feature;

use App\Enums\AccountCode;
use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Database\Factories\AccountingPeriodFactory;
use Database\Factories\UserFactory;
use Database\Seeders\EnhancedChartOfAccountsSeeder;
use Database\Seeders\OpeningBalanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_enhanced_seeder_creates_row_for_every_enum_case(): void
    {
        $this->seed(EnhancedChartOfAccountsSeeder::class);

        foreach (AccountCode::cases() as $account) {
            $row = ChartOfAccount::find($account->value);

            $this->assertNotNull($row, "Missing chart_of_accounts row for code {$account->value}");
            $this->assertSame($account->description(), $row->account_name);
            $this->assertSame($account->category(), $row->account_type->value);
            $this->assertTrue($row->is_active);
        }

        // One extra legacy account (1011) plus every enum case.
        $this->assertSame(count(AccountCode::cases()) + 1, ChartOfAccount::count());
    }

    public function test_seeder_does_not_create_legacy_income_summary_9999(): void
    {
        $this->seed(EnhancedChartOfAccountsSeeder::class);

        $this->assertDatabaseMissing('chart_of_accounts', ['account_code' => '9999']);

        $incomeSummary = ChartOfAccount::where('account_name', 'Income Summary')->first();
        $this->assertNotNull($incomeSummary);
        $this->assertSame(AccountCode::INCOME_SUMMARY->value, $incomeSummary->account_code);
        $this->assertSame('4201', $incomeSummary->account_code);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(EnhancedChartOfAccountsSeeder::class);
        $this->seed(EnhancedChartOfAccountsSeeder::class);

        $this->assertSame(count(AccountCode::cases()) + 1, ChartOfAccount::count());
    }

    public function test_opening_balance_seeder_books_balanced_posted_entry(): void
    {
        $this->seed(EnhancedChartOfAccountsSeeder::class);

        $fiscalYear = FiscalYear::factory()->create();
        AccountingPeriodFactory::new()->forMonth(1, $fiscalYear->id)->create();
        UserFactory::new()->admin()->create();

        $this->seed(OpeningBalanceSeeder::class);

        $entry = JournalEntry::where('reference_type', 'Opening Balance')->first();

        $this->assertNotNull($entry, 'Opening balance journal entry was not created');
        $this->assertSame('Posted', $entry->status->value);

        $lines = $entry->lines;
        $this->assertCount(2, $lines);

        $totalDebits = $lines->sum(fn ($line) => (float) $line->debit);
        $totalCredits = $lines->sum(fn ($line) => $line->credit === null ? 0 : (float) $line->credit);

        $this->assertSame(500000.0, $totalDebits);
        $this->assertEqualsWithDelta($totalDebits, $totalCredits, 0.001, 'Opening entry is not balanced');

        $cashLine = $lines->firstWhere('account_code', AccountCode::CASH_MYR->value);
        $capitalLine = $lines->firstWhere('account_code', AccountCode::CAPITAL_PAID_IN->value);

        $this->assertNotNull($cashLine);
        $this->assertNotNull($capitalLine);
        $this->assertEquals(500000.0, (float) $cashLine->debit);
        $this->assertEquals(500000.0, (float) $capitalLine->credit);

        // Ledger rows must be written by the posting path.
        $cashLedger = AccountLedger::where('account_code', AccountCode::CASH_MYR->value)->first();
        $capitalLedger = AccountLedger::where('account_code', AccountCode::CAPITAL_PAID_IN->value)->first();

        $this->assertNotNull($cashLedger);
        $this->assertNotNull($capitalLedger);
    }

    public function test_opening_balance_seeder_skips_when_no_admin_exists(): void
    {
        $this->seed(EnhancedChartOfAccountsSeeder::class);

        $fiscalYear = FiscalYear::factory()->create();

        $this->seed(OpeningBalanceSeeder::class);

        $this->assertNull(JournalEntry::where('reference_type', 'Opening Balance')->first());
        $this->assertTrue($fiscalYear->fresh()->isOpen());
    }
}
