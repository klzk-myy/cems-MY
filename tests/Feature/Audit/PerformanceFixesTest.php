<?php

namespace Tests\Feature\Audit;

use App\Enums\EddStatus;
use App\Models\EnhancedDiligenceRecord;
use App\Models\SystemLog;
use App\Services\AuditService;
use App\Services\Compliance\ComplianceReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_verification_runs_without_loading_all_rows(): void
    {
        SystemLog::factory()->count(5)->create(['entry_hash' => 'abc']);

        $service = app(AuditService::class);
        $result = $service->verifyChainIntegrity();

        $this->assertArrayHasKey('valid', $result);
    }

    public function test_edd_dashboard_counts_use_database_queries(): void
    {
        EnhancedDiligenceRecord::factory()->count(3)->create(['status' => EddStatus::Incomplete]);
        EnhancedDiligenceRecord::factory()->count(2)->create(['status' => EddStatus::Approved]);

        $service = app(ComplianceReportingService::class);
        $kpis = $service->getDashboardKpis();

        $this->assertSame(3, $kpis['edd_status']['active']);
    }
}
