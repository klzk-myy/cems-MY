<?php

namespace Tests\Feature\Audit;

use App\Models\SystemLog;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\ReportingService;
use App\Services\System\LogRotationService;
use App\Services\System\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
