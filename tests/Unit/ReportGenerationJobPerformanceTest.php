<?php

namespace Tests\Unit;

use App\Jobs\ReportGenerationJob;
use App\Services\ReportingService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ReportGenerationJobPerformanceTest extends TestCase
{
    public function test_report_generation_job_logs_performance()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Report generation job completed', Mockery::on(function ($context) {
                return isset($context['report_type']) && isset($context['duration_ms']);
            }));

        $mockService = Mockery::mock(ReportingService::class);
        $mockService->shouldReceive('generateReport')
            ->once()
            ->with('msb2', '2026-04-28')
            ->andReturn('reports/test.csv');

        $job = new ReportGenerationJob('msb2', '2026-04-28');
        $job->handle($mockService);
    }
}
