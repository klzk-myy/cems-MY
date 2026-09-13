<?php

namespace Tests\Unit\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Services\Accounting\MonthEndCloseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonthEndCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    private MonthEndCloseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MonthEndCloseService::class);
    }

    #[Test]
    public function pre_flight_checks_pass_when_period_exists_and_is_open(): void
    {
        // Use a date outside the test bootstrap's seeded current/prev/next
        // month periods so the test-created period is the only one for this
        // date (avoids the "other open periods" pre-flight failure).
        $date = Carbon::parse('2025-06-15');

        AccountingPeriod::firstOrCreate(
            ['period_code' => '2025-06'],
            [
                'fiscal_year_id' => null,
                'start_date' => $date->copy()->startOfMonth()->toDateString(),
                'end_date' => $date->copy()->endOfMonth()->toDateString(),
                'status' => AccountingPeriodStatus::Open,
            ]
        );

        $result = $this->service->preFlightChecks($date);

        $this->assertTrue($result['passed']);
        $this->assertEmpty($result['failures']);
    }

    #[Test]
    public function pre_flight_checks_fail_when_no_period_exists_for_date(): void
    {
        // Use a date far outside any seeded period range.
        $date = Carbon::parse('2030-06-15');

        $result = $this->service->preFlightChecks($date);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['failures']);
    }
}
