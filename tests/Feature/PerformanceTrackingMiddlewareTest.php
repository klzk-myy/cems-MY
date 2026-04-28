<?php

namespace Tests\Feature;

use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class PerformanceTrackingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_tracking_middleware_logs_request_performance()
    {
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Log::shouldReceive('info')
            ->once()
            ->with('Request performance', Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']) &&
                       isset($context['status']);
            }));

        $this->get('/dashboard');
    }

    public function test_performance_tracking_middleware_logs_slow_endpoints_when_threshold_exceeded()
    {
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Log::shouldReceive('info')
            ->once()
            ->with('Request performance', Mockery::any());

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getResponseTimeWarning')->andReturn('0.001');
        $this->app->instance(ThresholdService::class, $thresholdService);

        Log::shouldReceive('warning')
            ->once()
            ->with('Slow endpoint detected', Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']) &&
                       isset($context['threshold_ms']);
            }));

        $this->get('/dashboard');
    }
}
