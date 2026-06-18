<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\MonthEndPreCheckFailedException;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Accounting\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonthEndCloseTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => 'open',
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
            'status' => 'open',
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
            'status' => 'closed',
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
            'till_id' => 'TEST',
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
            'status' => 'open',
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
            'status' => 'open',
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
            'status' => 'open',
        ]);

        $this->service->closePeriod($date);

        $period->refresh();
        $this->assertEquals(AccountingPeriodStatus::Closed, $period->status);
        $this->assertNotNull($period->closed_at);
    }
}
