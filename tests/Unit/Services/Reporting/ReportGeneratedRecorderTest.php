<?php

namespace Tests\Unit\Services\Reporting;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Models\User;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGeneratedRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_generated_report_creates_record_with_correct_fields_and_defaults(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(ReportingService::class);
        $periodStart = Carbon::parse('2024-01-01')->startOfDay();
        $periodEnd = Carbon::parse('2024-01-31')->endOfDay();

        $record = $service->recordGeneratedReport(
            ReportType::Msb2,
            $periodStart,
            $periodEnd
        );

        $this->assertInstanceOf(ReportGenerated::class, $record);
        $this->assertSame(ReportType::Msb2, $record->report_type);
        $this->assertSame($periodStart->toDateString(), $record->period_start->toDateString());
        $this->assertSame($periodEnd->toDateString(), $record->period_end->toDateString());
        $this->assertSame($user->id, $record->generated_by);
        $this->assertSame('CSV', $record->file_format);
        $this->assertSame(ReportGeneratedStatus::Generated, $record->status);
    }

    public function test_record_generated_report_honors_status_override(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(ReportingService::class);

        $record = $service->recordGeneratedReport(
            ReportType::MonthEnd,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            'Failed'
        );

        $this->assertSame(ReportGeneratedStatus::Failed, $record->status);
    }

    public function test_record_generated_report_default_status_is_generated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(ReportingService::class);

        $record = $service->recordGeneratedReport(
            ReportType::Lmca,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        );

        $this->assertSame(ReportGeneratedStatus::Generated, $record->status);
    }
}
