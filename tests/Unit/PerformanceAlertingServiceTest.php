<?php

namespace Tests\Unit;

use App\Services\PerformanceAlertingService;
use App\Services\SystemAlertService;
use App\Services\ThresholdService;
use Mockery;
use Tests\TestCase;

class PerformanceAlertingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_check_response_time_triggers_alert_when_exceeded(): void
    {
        $alertService = Mockery::mock(SystemAlertService::class);
        $alertService->shouldReceive('warning')
            ->once()
            ->with(
                'Slow response time detected',
                Mockery::on(function ($options) {
                    return isset($options['source'])
                        && $options['source'] === 'performance'
                        && isset($options['metadata']['endpoint'])
                        && $options['metadata']['endpoint'] === '/api/test'
                        && $options['metadata']['duration_ms'] === 600.0;
                })
            );

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getResponseTimeWarning')->andReturn('500');

        $service = new PerformanceAlertingService($alertService, $thresholdService);
        $result = $service->checkResponseTime('/api/test', 600.0);

        $this->assertTrue($result);
    }

    public function test_check_response_time_returns_false_when_under_threshold(): void
    {
        $alertService = Mockery::mock(SystemAlertService::class);
        $alertService->shouldNotReceive('warning');

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getResponseTimeWarning')->andReturn('500');

        $service = new PerformanceAlertingService($alertService, $thresholdService);
        $result = $service->checkResponseTime('/api/test', 300.0);

        $this->assertFalse($result);
    }

    public function test_check_cache_hit_rate_triggers_alert_when_low(): void
    {
        $alertService = Mockery::mock(SystemAlertService::class);
        $alertService->shouldReceive('warning')
            ->once()
            ->with(
                'Low cache hit rate',
                Mockery::on(function ($options) {
                    return isset($options['source'])
                        && $options['source'] === 'performance'
                        && isset($options['metadata']['hit_rate'])
                        && $options['metadata']['hit_rate'] === 50.0;
                })
            );

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getCacheHitRateWarning')->andReturn('70');

        $service = new PerformanceAlertingService($alertService, $thresholdService);
        $result = $service->checkCacheHitRate(50.0);

        $this->assertTrue($result);
    }

    public function test_check_cache_hit_rate_returns_false_when_healthy(): void
    {
        $alertService = Mockery::mock(SystemAlertService::class);
        $alertService->shouldNotReceive('warning');

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getCacheHitRateWarning')->andReturn('70');

        $service = new PerformanceAlertingService($alertService, $thresholdService);
        $result = $service->checkCacheHitRate(85.0);

        $this->assertFalse($result);
    }

    public function test_check_query_time_triggers_alert_when_exceeded(): void
    {
        $alertService = Mockery::mock(SystemAlertService::class);
        $alertService->shouldReceive('warning')
            ->once()
            ->with(
                'Slow query detected',
                Mockery::on(function ($options) {
                    return isset($options['source'])
                        && $options['source'] === 'performance'
                        && isset($options['metadata']['query_time_ms'])
                        && $options['metadata']['query_time_ms'] === 150.0;
                })
            );

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getQueryTimeWarning')->andReturn('100');

        $service = new PerformanceAlertingService($alertService, $thresholdService);
        $result = $service->checkQueryTime(150.0);

        $this->assertTrue($result);
    }

    public function test_check_query_time_returns_false_when_under_threshold(): void
    {
        $alertService = Mockery::mock(SystemAlertService::class);
        $alertService->shouldNotReceive('warning');

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getQueryTimeWarning')->andReturn('100');

        $service = new PerformanceAlertingService($alertService, $thresholdService);
        $result = $service->checkQueryTime(50.0);

        $this->assertFalse($result);
    }
}
