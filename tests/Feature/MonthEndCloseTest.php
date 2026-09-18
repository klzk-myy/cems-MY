<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountMappingKey;
use App\Enums\UserRole;
use App\Exceptions\Domain\MonthEndPreCheckFailedException;
use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Accounting\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonthEndCloseTest extends TestCase
{
    use DatabaseTransactions;

    protected MonthEndCloseService $service;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'username' => 'manager',
            'email' => 'manager@test.com',
            'password_hash' => bcrypt('password'),
            'role' => UserRole::Manager->value,
            'is_active' => true,
        ]);

        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true]
        );

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

        $this->service = app(MonthEndCloseService::class);
    }

    protected function createFiscalYearAndPeriod(string $date): AccountingPeriod
    {
        $parsed = Carbon::parse($date);
        $fiscalYear = FiscalYear::factory()->create([
            'year_code' => (string) $parsed->year,
            'start_date' => $parsed->startOfYear()->toDateString(),
            'end_date' => $parsed->endOfYear()->toDateString(),
            'status' => 'Open',
        ]);

        return AccountingPeriod::factory()->create([
            'period_code' => $parsed->format('Y-m'),
            'start_date' => $parsed->startOfMonth()->toDateString(),
            'end_date' => $parsed->endOfMonth()->toDateString(),
            'period_type' => 'month',
            'status' => 'Open',
            'fiscal_year_id' => $fiscalYear->id,
        ]);
    }

    #[Test]
    public function pre_flight_checks_passes_with_open_period(): void
    {
        $date = Carbon::parse('2026-03-31');

        $period = AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Open',
        ]);

        $result = $this->service->preFlightChecks($date);

        $this->assertTrue($result['passed'], 'Pre-flight should pass but got failures: '.json_encode($result['failures']));
        $this->assertEmpty($result['failures']);
    }

    #[Test]
    public function pre_flight_checks_fails_when_no_period_exists(): void
    {
        $date = Carbon::parse('2026-03-31');

        $result = $this->service->preFlightChecks($date);

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('No accounting period found', $result['failures'][0]);
    }

    #[Test]
    public function pre_flight_checks_fails_when_period_already_closed(): void
    {
        $date = Carbon::parse('2026-03-31');
        $period = AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Closed',
        ]);

        $result = $this->service->preFlightChecks($date);

        $this->assertFalse($result['passed'], 'Pre-flight should fail for closed period');
        $this->assertStringContainsString('already closed', $result['failures'][0]);
    }

    #[Test]
    public function pre_flight_checks_fails_when_pending_entries_exist(): void
    {
        $date = Carbon::parse('2026-03-31');
        $period = $this->createFiscalYearAndPeriod($date->toDateString());

        CurrencyPosition::factory()->create([
            'currency_code' => 'USD',
            'branch_id' => $this->createTestBranch()->id,
            'balance' => '1000.00',
        ]);

        $result = $this->service->preFlightChecks($date);

        $this->assertFalse($result['passed']);
    }

    #[Test]
    public function run_month_end_closing_throws_when_pre_check_fails(): void
    {
        $date = Carbon::parse('2026-03-31');

        $this->expectException(MonthEndPreCheckFailedException::class);

        $this->service->runMonthEndClosing($date, $this->manager);
    }

    #[Test]
    public function close_period_creates_next_period(): void
    {
        $date = Carbon::parse('2026-03-31');
        $period = AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Open',
            'fiscal_year_id' => null,
        ]);

        $result = $this->service->closePeriod($date);

        $this->assertEquals($period->id, $result['period_id']);
        $this->assertEquals('2026-03', $result['period_code']);

        $period->refresh();
        $this->assertEquals(AccountingPeriodStatus::Closed, $period->status);
    }

    #[Test]
    public function get_month_end_status_returns_correct_data(): void
    {
        $date = Carbon::parse('2026-03-31');
        AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Open',
        ]);

        $status = $this->service->getMonthEndStatus($date);

        $this->assertEquals('2026-03-31', $status['date']);
        $this->assertTrue($status['has_period']);
        $this->assertEquals(AccountingPeriodStatus::Open, $status['period_status']);
        $this->assertEquals('2026-03', $status['period_code']);
        $this->assertFalse($status['revaluation_run']);
    }

    #[Test]
    public function close_period_sets_period_to_closed(): void
    {
        $date = Carbon::parse('2026-03-31');
        $period = AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Open',
        ]);

        $this->service->closePeriod($date);

        $period->refresh();
        $this->assertEquals(AccountingPeriodStatus::Closed, $period->status);
        $this->assertNotNull($period->closed_at);
    }

    #[Test]
    public function close_period_is_idempotent_for_already_closed_periods(): void
    {
        $date = Carbon::parse('2026-03-31');
        $period = AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Open',
        ]);

        $first = $this->service->closePeriod($date);
        $this->assertArrayNotHasKey('note', $first);

        // A rerun (scheduled monthly task) must not fail; it reports success with a note.
        $second = $this->service->closePeriod($date);

        $this->assertEquals($period->id, $second['period_id']);
        $this->assertEquals('2026-03', $second['period_code']);
        $this->assertArrayHasKey('note', $second);
        $this->assertStringContainsString('already closed', (string) ($second['note'] ?? ''));
    }

    #[Test]
    public function close_period_records_closer_when_user_is_authenticated(): void
    {
        $date = Carbon::parse('2026-03-31');
        AccountingPeriod::factory()->create([
            'period_code' => '2026-03',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'period_type' => 'month',
            'status' => 'Open',
        ]);

        $this->actingAs($this->manager);
        $result = $this->service->closePeriod($date);

        $period = AccountingPeriod::findOrFail($result['period_id']);
        $this->assertEquals(AccountingPeriodStatus::Closed, $period->status);
        $this->assertEquals($this->manager->id, $period->closed_by);
    }
}
