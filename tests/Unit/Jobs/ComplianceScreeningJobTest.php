<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ComplianceScreeningJob;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceScreeningJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_serialized(): void
    {
        $job = new ComplianceScreeningJob(1);

        $this->assertEquals(1, $job->customerId);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(600, $job->timeout);
    }

    #[Test]
    public function it_calls_screen_customer_for_existing_customer(): void
    {
        $customer = Customer::factory()->create();

        $screeningService = $this->mock(CustomerScreeningService::class);
        $screeningService->shouldReceive('screenCustomer')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($customer) {
                return $arg instanceof Customer && $arg->id === $customer->id;
            }));

        $thresholdService = $this->mock(ThresholdService::class);
        $thresholdService->shouldReceive('getJobDurationWarning')
            ->andReturn(1000);

        $job = new ComplianceScreeningJob($customer->id);
        $job->handle($screeningService, $thresholdService);
    }

    #[Test]
    public function it_skips_nonexistent_customer(): void
    {
        $screeningService = $this->mock(CustomerScreeningService::class);
        $screeningService->shouldNotReceive('screenCustomer');

        $thresholdService = $this->mock(ThresholdService::class);
        $thresholdService->shouldReceive('getJobDurationWarning')
            ->andReturn(1000);

        $job = new ComplianceScreeningJob(999999);
        $job->handle($screeningService, $thresholdService);
    }

    #[Test]
    public function it_has_correct_tags(): void
    {
        $job = new ComplianceScreeningJob(1);

        $this->assertEquals(['compliance', 'screening'], $job->tags());
    }
}
