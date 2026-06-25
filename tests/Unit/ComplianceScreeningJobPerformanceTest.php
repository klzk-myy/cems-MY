<?php

namespace Tests\Unit;

use App\Jobs\ComplianceScreeningJob;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceScreeningJobPerformanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function compliance_screening_job_logs_performance()
    {
        $customer = Customer::factory()->create();

        Log::shouldReceive('info')
            ->once()
            ->with('Compliance screening job completed', Mockery::on(function ($context) {
                return isset($context['customer_id']) && isset($context['duration_ms']);
            }));

        $job = new ComplianceScreeningJob($customer->id);
        $job->handle(app(CustomerScreeningService::class), app(ThresholdService::class));
    }

    #[Test]
    public function slow_compliance_screening_job_logs_warning_when_threshold_exceeded()
    {
        $customer = Customer::factory()->create();

        Log::shouldReceive('info')
            ->once()
            ->with('Compliance screening job completed', Mockery::on(function ($context) {
                return isset($context['customer_id']) && isset($context['duration_ms']);
            }));

        $mockThreshold = Mockery::mock(ThresholdService::class);
        $mockThreshold->shouldReceive('getJobDurationWarning')->andReturn('0.001');

        Log::shouldReceive('warning')
            ->once()
            ->with('Slow compliance screening job', Mockery::on(function ($context) {
                return isset($context['customer_id']) &&
                       isset($context['duration_ms']) &&
                       isset($context['threshold_ms']);
            }));

        $job = new ComplianceScreeningJob($customer->id);
        $job->handle(app(CustomerScreeningService::class), $mockThreshold);
    }
}
