<?php

namespace Tests\Feature\Audit;

use App\Enums\SystemHealthCheckStatus;
use App\Jobs\Audit\SealAuditHashJob;
use App\Models\SystemLog;
use App\Services\AuditService;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\ReportingService;
use App\Services\System\LogRotationService;
use App\Services\System\RateLimitService;
use App\Services\System\SystemHealthService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EdgeCaseFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_cidr_mask_returns_false(): void
    {
        $service = app(RateLimitService::class);
        $this->assertFalse($service->ipInCidr('192.168.1.1', '192.168.0.0/33'));
        $this->assertFalse($service->ipInCidr('192.168.1.1', '192.168.0.0/abc'));
    }

    public function test_archive_old_logs_creates_file_and_deletes_rows(): void
    {
        $cutoff = now()->subDays(30);
        SystemLog::factory()->create(['created_at' => now()->subYear()]);
        $this->assertSame(1, SystemLog::where('created_at', '<', $cutoff)->count());

        $service = app(LogRotationService::class);
        $result = $service->archiveOldLogs(30);

        $this->assertSame(1, $result['archived']);
        $this->assertFileExists($result['path']);
        $this->assertSame(0, SystemLog::where('created_at', '<', $cutoff)->count());
        $this->assertSame(1, SystemLog::count());
    }

    public function test_export_service_counts_only_deleted_files(): void
    {
        $service = app(ExportService::class);
        $path = $service->getExportPath('stale_report.csv');
        file_put_contents($path, 'data');
        touch($path, now()->subDay()->timestamp);

        $deleted = $service->cleanupOldReports(0);

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFileDoesNotExist($path);
    }

    public function test_msb2_report_generation_succeeds(): void
    {
        $filePath = app(ReportingService::class)->generateMSB2(today()->subDay()->toDateString());

        $this->assertIsString($filePath);
        $this->assertFileExists(Storage::path($filePath));
    }

    public function test_disk_health_handles_zero_total_space(): void
    {
        $service = app(SystemHealthService::class);
        $result = $service->checkDiskSpace('/nonexistent_path_for_test');

        $this->assertSame(SystemHealthCheckStatus::Warning->value, $result['status']);
    }

    public function test_seal_job_throws_when_predecessor_missing(): void
    {
        $predecessor = SystemLog::factory()->create(['entry_hash' => 'existing_hash']);
        $log = SystemLog::factory()->create(['entry_hash' => null]);
        $predecessorId = $predecessor->id;

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) use ($predecessorId): void {
            if (str_contains($event->sql, 'entry_hash') && str_contains($event->sql, 'order by')) {
                DB::delete('delete from system_logs where id = ?', [$predecessorId]);
                Event::forget(QueryExecuted::class);
            }
        });

        $this->expectException(\RuntimeException::class);
        (new SealAuditHashJob($log->id))->handle(app(AuditService::class));
    }

    public function test_invalid_quarter_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ReportingService::class)->generateQuarterlyLargeValueReport('not-a-quarter');
    }
}
