<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountMappingKey;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Period close resolves P&L summary / retained earnings accounts
        // through the account_mappings table — the fixture accounts must
        // exist first (account_mappings.account_code FKs to the chart).
        foreach (['4201', '4202', '4300'] as $code) {
            // updateOrCreate: some of these codes (e.g. 4201 INCOME_SUMMARY)
            // are already seeded from the AccountCode enum by SchemaSeeder.
            ChartOfAccount::updateOrCreate(
                ['account_code' => $code],
                [
                    'account_name' => "Fixture Account {$code}",
                    'account_type' => 'Equity',
                    'account_class' => 'Equity',
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            AccountMappingKey::CloseRevenueSummary->value => '4201',
            AccountMappingKey::CloseExpenseSummary->value => '4202',
            AccountMappingKey::CloseRetainedEarnings->value => '4300',
        ] as $key => $code) {
            AccountMapping::updateOrCreate(['key' => $key], ['account_code' => $code]);
        }
    }

    public function test_period_close_uses_enum_value(): void
    {
        $period = AccountingPeriod::factory()->create([
            'status' => AccountingPeriodStatus::Open,
        ]);
        $user = User::factory()->create();

        $service = app(PeriodCloseService::class);
        $result = $service->closePeriod($period, $user->id);

        $this->assertTrue($result['success']);
        $this->assertSame(AccountingPeriodStatus::Closed->value, $period->fresh()->status->value);
    }

    public function test_period_close_zeros_revenue_and_expense_accounts(): void
    {
        $period = AccountingPeriod::factory()->create();
        // 4100/5100 already exist in the enum-backed chart of accounts
        // (Retained Earnings / Revaluation Gain); update their type instead
        // of inserting duplicates.
        $revenue = ChartOfAccount::updateOrCreate(
            ['account_code' => '4100'],
            ['account_type' => 'Revenue', 'is_active' => true]
        );
        $expense = ChartOfAccount::updateOrCreate(
            ['account_code' => '5100'],
            ['account_type' => 'Expense', 'is_active' => true]
        );

        // Seed balances
        AccountLedger::factory()->create([
            'account_code' => '4100',
            'credit' => '1000.00',
            'debit' => '0',
            'running_balance' => '1000.00',
            'entry_date' => $period->end_date,
        ]);
        AccountLedger::factory()->create([
            'account_code' => '5100',
            'debit' => '300.00',
            'credit' => '0',
            'running_balance' => '300.00',
            'entry_date' => $period->end_date,
        ]);

        $user = User::factory()->create();
        $service = app(PeriodCloseService::class);
        $result = $service->closePeriod($period, $user->id);

        $entry = $result['closing_entries'][0];
        $lines = $entry->lines->keyBy('account_code');

        $this->assertSame('1000.0000', (string) $lines['4100']->debit);
        $this->assertSame('300.0000', (string) $lines['5100']->credit);
    }
}
