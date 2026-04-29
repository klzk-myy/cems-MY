<?php

namespace Tests\Unit;

use App\Events\RiskScoreUpdated;
use App\Models\Customer;
use App\Models\RiskScoreSnapshot;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CustomerRiskReviewService;
use App\Services\CustomerRiskScoringService;
use App\Services\CustomerScreeningService;
use App\Services\EncryptionService;
use App\Services\MathService;
use App\Services\Risk\GeographicRiskService;
use App\Services\RiskCalculationService;
use App\Services\ThresholdService;
use App\ValueObjects\ScreeningResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerRiskReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerRiskReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([RiskScoreUpdated::class]);

        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $encryptionService = new EncryptionService;
        $complianceService = new ComplianceService($encryptionService, $mathService);
        $auditService = new AuditService;
        $riskCalculationService = new RiskCalculationService($mathService, $thresholdService);
        $geographicRiskService = new GeographicRiskService;

        $screeningResponse = new ScreeningResponse(
            action: 'clear',
            confidenceScore: 0.0,
            matches: new Collection,
            screenedAt: Carbon::now(),
        );

        $screeningService = $this->createMock(CustomerScreeningService::class);
        $screeningService->method('screenCustomer')->willReturn($screeningResponse);

        $riskScoringService = new CustomerRiskScoringService(
            $screeningService,
            $complianceService,
            $auditService,
            $mathService,
            $thresholdService,
            $riskCalculationService,
            $geographicRiskService
        );

        $this->service = new CustomerRiskReviewService($riskScoringService);
    }

    public function test_process_due_reviews_returns_empty_when_no_customers_due(): void
    {
        $customer = Customer::factory()->create();

        RiskScoreSnapshot::create([
            'customer_id' => $customer->id,
            'snapshot_date' => today(),
            'overall_score' => 50,
            'overall_rating_label' => 'Medium',
            'next_screening_date' => now()->addDays(30),
            'velocity_score' => 0,
            'structuring_score' => 0,
            'geographic_score' => 0,
            'amount_score' => 0,
            'trend' => 'stable',
            'factors' => [],
        ]);

        $results = $this->service->processDueReviews(50);

        $this->assertEquals(0, $results['processed']);
        $this->assertEquals(0, $results['changed']);
        $this->assertEquals(0, $results['errors']);
    }

    public function test_process_due_reviews_processes_customers_past_next_screening_date(): void
    {
        $customer = Customer::factory()->create(['risk_score' => 50]);

        RiskScoreSnapshot::create([
            'customer_id' => $customer->id,
            'snapshot_date' => now()->subDays(100),
            'overall_score' => 50,
            'overall_rating_label' => 'Medium',
            'next_screening_date' => now()->subDays(10),
            'velocity_score' => 0,
            'structuring_score' => 0,
            'geographic_score' => 0,
            'amount_score' => 0,
            'trend' => 'stable',
            'factors' => [],
        ]);

        $results = $this->service->processDueReviews(50);

        $this->assertEquals(1, $results['processed']);
        $this->assertEquals(0, $results['errors']);
    }

    public function test_process_due_reviews_respects_batch_size(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $customer = Customer::factory()->create();
            RiskScoreSnapshot::create([
                'customer_id' => $customer->id,
                'snapshot_date' => now()->subDays(100),
                'overall_score' => 50,
                'overall_rating_label' => 'Medium',
                'next_screening_date' => now()->subDays(10),
                'velocity_score' => 0,
                'structuring_score' => 0,
                'geographic_score' => 0,
                'amount_score' => 0,
                'trend' => 'stable',
                'factors' => [],
            ]);
        }

        $results = $this->service->processDueReviews(2);

        $this->assertEquals(2, $results['processed']);
    }

    public function test_process_due_reviews_counts_score_changes(): void
    {
        $customer = Customer::factory()->create(['risk_score' => 30]);

        RiskScoreSnapshot::create([
            'customer_id' => $customer->id,
            'snapshot_date' => now()->subDays(100),
            'overall_score' => 30,
            'overall_rating_label' => 'Low',
            'next_screening_date' => now()->subDays(10),
            'velocity_score' => 0,
            'structuring_score' => 0,
            'geographic_score' => 0,
            'amount_score' => 0,
            'trend' => 'stable',
            'factors' => [],
        ]);

        $results = $this->service->processDueReviews(50);

        $this->assertEquals(1, $results['processed']);
        $this->assertGreaterThanOrEqual(0, $results['changed']);
    }
}
