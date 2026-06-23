<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RescreenHighRiskCustomersJob;
use App\Models\Customer;
use App\Services\CustomerScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RescreenHighRiskCustomersJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_serialized(): void
    {
        $job = new RescreenHighRiskCustomersJob;

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(600, $job->timeout);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    #[Test]
    public function it_calls_screen_customer_for_high_risk_customers(): void
    {
        $customer = Customer::factory()->create(['risk_score' => 80]);

        $screeningService = $this->mock(CustomerScreeningService::class);
        $screeningService->shouldReceive('screenCustomer')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($customer) {
                return $arg instanceof Customer && $arg->id === $customer->id;
            }));

        $job = new RescreenHighRiskCustomersJob;
        $job->handle($screeningService);
    }

    #[Test]
    public function it_has_correct_tags(): void
    {
        $job = new RescreenHighRiskCustomersJob;

        $this->assertEquals(['sanctions', 'sanctions-rescreen', 'high-risk'], $job->tags());
    }
}
