<?php

namespace Tests\Unit;

use App\Services\PerformanceBaselineService;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PerformanceBaselineServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_baseline_returns_default_values()
    {
        Cache::shouldReceive('get')
            ->with('performance_baseline', Mockery::any())
            ->andReturn([
                'response_time_ms' => 200,
                'cache_hit_rate' => 80.0,
                'queries_per_request' => 5,
                'memory_mb' => 128,
            ]);

        $thresholdService = Mockery::mock(ThresholdService::class);
        $service = new PerformanceBaselineService($thresholdService);

        $baseline = $service->getBaseline();

        $this->assertEquals(200, $baseline['response_time_ms']);
        $this->assertEquals(80.0, $baseline['cache_hit_rate']);
    }

    public function test_set_baseline_stores_values_in_cache()
    {
        Cache::shouldReceive('forever')
            ->with('performance_baseline', Mockery::any())
            ->once();

        $thresholdService = Mockery::mock(ThresholdService::class);
        $service = new PerformanceBaselineService($thresholdService);

        $service->setBaseline(['response_time_ms' => 150]);

        $this->assertTrue(true);
    }

    public function test_compare_response_time_calculates_variance()
    {
        Cache::shouldReceive('get')
            ->with('performance_baseline', Mockery::any())
            ->andReturn(['response_time_ms' => 200, 'cache_hit_rate' => 80.0, 'queries_per_request' => 5, 'memory_mb' => 128]);

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getResponseTimeWarning')->andReturn('500');

        $service = new PerformanceBaselineService($thresholdService);

        $result = $service->compareResponseTime(240);

        $this->assertEquals(200, $result['baseline']);
        $this->assertEquals(240, $result['current']);
        $this->assertEquals(20, $result['variance_percent']);
        $this->assertEquals('warning', $result['status']);
    }

    public function test_compare_response_time_detects_degradation()
    {
        Cache::shouldReceive('get')
            ->with('performance_baseline', Mockery::any())
            ->andReturn(['response_time_ms' => 200, 'cache_hit_rate' => 80.0, 'queries_per_request' => 5, 'memory_mb' => 128]);

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getResponseTimeWarning')->andReturn('500');

        $service = new PerformanceBaselineService($thresholdService);

        $result = $service->compareResponseTime(300);

        $this->assertEquals(50, $result['variance_percent']);
        $this->assertEquals('degraded', $result['status']);
    }

    public function test_compare_cache_hit_rate_calculates_variance()
    {
        Cache::shouldReceive('get')
            ->with('performance_baseline', Mockery::any())
            ->andReturn(['response_time_ms' => 200, 'cache_hit_rate' => 80.0, 'queries_per_request' => 5, 'memory_mb' => 128]);

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getCacheHitRateWarning')->andReturn('70');

        $service = new PerformanceBaselineService($thresholdService);

        $result = $service->compareCacheHitRate(70.0);

        $this->assertEquals(80.0, $result['baseline']);
        $this->assertEquals(70.0, $result['current']);
        $this->assertEquals(12.5, $result['variance_percent']);
        $this->assertEquals('warning', $result['status']);
    }

    public function test_compare_queries_per_request()
    {
        Cache::shouldReceive('get')
            ->with('performance_baseline', Mockery::any())
            ->andReturn(['response_time_ms' => 200, 'cache_hit_rate' => 80.0, 'queries_per_request' => 5, 'memory_mb' => 128]);

        $thresholdService = Mockery::mock(ThresholdService::class);
        $service = new PerformanceBaselineService($thresholdService);

        $result = $service->compareQueriesPerRequest(6);

        $this->assertEquals(5, $result['baseline']);
        $this->assertEquals(6, $result['current']);
        $this->assertTrue($result['exceeds_baseline']);
    }
}
