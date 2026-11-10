<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PerformanceTrackingMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.logging' => false]);
    }

    #[Test]
    public function performance_tracking_middleware_logs_request_performance()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // A spy tolerates the rest of the request pipeline's logging; a strict
        // shouldReceive(...)->once() mock dies on the first non-matching call
        // and the expectation reports 0 invocations.
        $spy = Log::spy();

        $this->get('/dashboard');

        $spy->shouldHaveReceived('info')
            ->once()
            ->with('Request performance', Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']) &&
                       isset($context['status']);
            }));
    }

    #[Test]
    public function performance_tracking_middleware_logs_slow_endpoints_when_threshold_exceeded()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $thresholdService = Mockery::mock(ThresholdService::class);
        $thresholdService->shouldReceive('getResponseTimeWarning')->andReturn('0.001');
        $this->app->instance(ThresholdService::class, $thresholdService);

        $spy = Log::spy();

        $this->get('/dashboard');

        $spy->shouldHaveReceived('info')
            ->once()
            ->with('Request performance', Mockery::any());

        $spy->shouldHaveReceived('warning')
            ->once()
            ->with('Slow endpoint detected', Mockery::on(function ($context) {
                return isset($context['url']) &&
                       isset($context['method']) &&
                       isset($context['duration_ms']) &&
                       isset($context['threshold_ms']);
            }));
    }
}
