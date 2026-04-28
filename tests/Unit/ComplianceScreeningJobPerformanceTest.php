<?php

namespace Tests\Unit;

use App\Jobs\ComplianceScreeningJob;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ComplianceScreeningJobPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_screening_job_logs_performance()
    {
        $customer = Customer::factory()->create();

        // Expect info and also warning (performance monitoring)
        Log::shouldReceive('info')
            ->once()
            ->with('Compliance screening job completed', \Mockery::on(function ($context) {
                return isset($context['customer_id']) && isset($context['duration_ms']);
            }));

        Log::shouldReceive('warning')
            ->once()
            ->with('Slow compliance screening job', \Mockery::on(function ($context) {
                return isset($context['customer_id']) && isset($context['duration_ms']);
            }));

        $job = new ComplianceScreeningJob($customer->id);
        $job->handle(app(CustomerScreeningService::class));
    }

    public function test_slow_compliance_screening_job_logs_warning()
    {
        $customer = Customer::factory()->create();

        // Both info and warning expected
        Log::shouldReceive('info')
            ->once()
            ->with('Compliance screening job completed', \Mockery::on(function ($context) {
                return isset($context['customer_id']) && isset($context['duration_ms']);
            }));

        Log::shouldReceive('warning')
            ->once()
            ->with('Slow compliance screening job', \Mockery::on(function ($context) {
                return isset($context['customer_id']) && isset($context['duration_ms']);
            }));

        $job = new ComplianceScreeningJob($customer->id);
        $job->handle(app(CustomerScreeningService::class));
    }
}
