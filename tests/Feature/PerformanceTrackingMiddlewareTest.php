<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PerformanceTrackingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_tracking_middleware_logs_request_performance()
    {
        // Allow error logs (e.g., from other middleware or exceptions)
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Log::shouldReceive('info')
            ->once()
            ->with('Request performance', \Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']) &&
                       isset($context['status']);
            }));

        // Also expect warning (always logged)
        Log::shouldReceive('warning')
            ->once()
            ->with('Slow endpoint detected', \Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']);
            }));

        $this->get('/dashboard');
    }

    public function test_performance_tracking_middleware_logs_slow_endpoints()
    {
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Both info and warning expected
        Log::shouldReceive('info')
            ->once()
            ->with('Request performance', \Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']) &&
                       isset($context['status']);
            }));

        Log::shouldReceive('warning')
            ->once()
            ->with('Slow endpoint detected', \Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['duration_ms']);
            }));

        $this->get('/dashboard');
    }
}
