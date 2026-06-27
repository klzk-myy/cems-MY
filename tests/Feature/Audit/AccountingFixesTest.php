<?php

namespace Tests\Feature\Audit;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\AccountLedger;
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

        // Remove seeded account so the test can reuse the code expected by the regression test.
        ChartOfAccount::where('account_code', '5100')->delete();

        // The new closing logic requires a retained earnings account to exist.
        ChartOfAccount::factory()->create([
            'account_code' => '3100',
            'account_name' => 'Retained Earnings',
            'account_type' => 'Equity',
            'account_class' => 'Equity',
        ]);
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
        $revenue = ChartOfAccount::factory()->create(['account_type' => 'Revenue', 'account_code' => '4100']);
        $expense = ChartOfAccount::factory()->create(['account_type' => 'Expense', 'account_code' => '5100']);

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
