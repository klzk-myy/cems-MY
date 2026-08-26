<?php

namespace Tests\Feature;

use App\Enums\ReportGeneratedStatus;
use App\Enums\ReportRunStatus;
use App\Enums\ReportType;
use App\Models\ReportGenerated;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessDueReportSchedulesTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function due_schedule_generates_report_run_and_artifact_and_advances_next_run(): void
    {
        $user = User::factory()->create();
        config(['cems.system_user_id' => $user->id]);

        $schedule = ReportSchedule::create([
            'report_type' => ReportType::Msb2,
            'cron_expression' => '0 0 * * *',
            'parameters' => null,
            'is_active' => true,
            'notification_recipients' => null,
            'created_by' => $user->id,
        ]);
        $schedule->forceFill(['next_run_at' => now()->subHour()])->save();

        $this->artisanCommand('reports:process-schedules')->assertSuccessful();

        $run = ReportRun::where('schedule_id', $schedule->id)->first();
        $this->assertNotNull($run, 'Expected a report run for the due schedule');
        $this->assertSame(ReportRunStatus::Completed, $run->status);
        $this->assertNotNull($run->file_path);
        $this->assertSame(ReportType::Msb2, $run->report_type);

        $artifact = ReportGenerated::where('file_path', $run->file_path)->first();
        $this->assertNotNull($artifact, 'Scheduled artifact should be registered in reports_generated so archival sweeps it');
        $this->assertSame(ReportGeneratedStatus::Generated, $artifact->status);
        $this->assertEquals($run->parameters['date'], $artifact->period_start->toDateString());

        $schedule->refresh();
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture(), 'next_run_at should be advanced past now');
        $this->assertNotNull($schedule->last_run_at);
    }

    #[Test]
    public function failing_generator_is_isolated_and_other_schedules_still_process(): void
    {
        config(['cems.license_number' => null]);

        $user = User::factory()->create();
        config(['cems.system_user_id' => $user->id]);

        $failing = ReportSchedule::create([
            'report_type' => ReportType::Lmca,
            'cron_expression' => '0 0 1 * *',
            'parameters' => null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $failing->forceFill(['next_run_at' => now()->subHour()])->save();

        $healthy = ReportSchedule::create([
            'report_type' => ReportType::Msb2,
            'cron_expression' => '0 0 * * *',
            'parameters' => null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $healthy->forceFill(['next_run_at' => now()->subHour()])->save();

        $this->artisanCommand('reports:process-schedules')->assertSuccessful();

        $failedRun = ReportRun::where('schedule_id', $failing->id)->first();
        $this->assertNotNull($failedRun);
        $this->assertSame(ReportRunStatus::Failed, $failedRun->status);
        $this->assertNotNull($failedRun->error_message);

        $healthyRun = ReportRun::where('schedule_id', $healthy->id)->first();
        $this->assertNotNull($healthyRun);
        $this->assertSame(ReportRunStatus::Completed, $healthyRun->status, 'Healthy run failed with: '.$healthyRun->error_message);

        $failing->refresh();
        $healthy->refresh();
        $this->assertTrue($failing->next_run_at->isFuture(), 'Failed schedule should still advance next_run_at');
        $this->assertTrue($healthy->next_run_at->isFuture());
    }

    #[Test]
    public function generation_writes_audit_row(): void
    {
        $user = User::factory()->create();
        config(['cems.system_user_id' => $user->id]);

        $schedule = ReportSchedule::create([
            'report_type' => ReportType::Msb2,
            'cron_expression' => '0 0 * * *',
            'parameters' => null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $schedule->forceFill(['next_run_at' => now()->subHour()])->save();

        $this->artisanCommand('reports:process-schedules')->assertSuccessful();

        $audit = SystemLog::where('action', 'regulatory_report_generated')
            ->where('entity_type', 'ReportGenerated')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'Generation completion should be audit logged');
        $newValues = is_array($audit->new_values) ? $audit->new_values : (array) json_decode((string) $audit->new_values, true);
        $this->assertSame(ReportType::Msb2->value, $newValues['report_type'] ?? null);
        $this->assertSame($user->id, $audit->user_id);
    }
}
