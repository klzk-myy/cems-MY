<?php

namespace Tests\Unit;

use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CustomerRiskScoringService;
use App\Services\CustomerScreeningService;
use App\Services\EncryptionService;
use App\Services\MathService;
use App\Services\RiskCalculationService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CustomerRiskScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerRiskScoringService $service;

    private MathService $mathService;

    private ThresholdService $thresholdService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mathService = new MathService;
        $this->thresholdService = new ThresholdService;
        $encryptionService = new EncryptionService;
        $complianceService = new ComplianceService($encryptionService, $this->mathService);
        $auditService = new AuditService;
        $riskCalculationService = new RiskCalculationService($this->mathService, $this->thresholdService);

        $screeningService = $this->createMock(CustomerScreeningService::class);

        $this->service = new CustomerRiskScoringService(
            $screeningService,
            $complianceService,
            $auditService,
            $this->mathService,
            $this->thresholdService,
            $riskCalculationService
        );
    }

    public function test_calculate_velocity_score_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_velocity_score_with_small_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create(['amount_local' => '1000', 'created_at' => now()]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_velocity_score_with_medium_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create(['amount_local' => '15000', 'created_at' => now()]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertGreaterThanOrEqual(10, $result);
    }

    public function test_calculate_velocity_score_with_high_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create(['amount_local' => '60000', 'created_at' => now()]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertGreaterThanOrEqual(30, $result);
    }

    public function test_calculate_velocity_score_max_is_40(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create(['amount_local' => '60000', 'created_at' => now()->addMinutes($i)]);
        }

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertLessThanOrEqual(40, $result);
    }

    public function test_calculate_structuring_score_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_structuring_score_with_single_transaction(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create(['amount_local' => '2000', 'created_at' => now()]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_structuring_score_with_three_transactions_same_hour(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create(['amount_local' => '2000', 'created_at' => now()->addMinutes($i)]);
        }

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertGreaterThanOrEqual(25, $result);
    }

    public function test_calculate_structuring_score_max_is_30(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create(['amount_local' => '2000', 'created_at' => now()->addMinutes($i)]);
        }

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $customer->id);

        $this->assertLessThanOrEqual(30, $result);
    }

    public function test_calculate_amount_score_returns_zero_for_no_transactions(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateAmountScore');
        $method->setAccessible(true);

        $customer = new Customer;

        $result = $method->invoke($this->service, new Collection, $customer);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_amount_score_for_large_max_transaction(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateAmountScore');
        $method->setAccessible(true);

        $transaction = new Transaction;
        $transaction->amount_local = '60000';

        $customer = new Customer;

        $result = $method->invoke($this->service, new Collection([$transaction]), $customer);

        $this->assertGreaterThanOrEqual(30, $result);
    }

    public function test_extract_risk_factors_includes_pep_customer(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractRiskFactors');
        $method->setAccessible(true);

        $customer = new Customer;
        $customer->pep_status = true;

        $scores = [
            'velocity' => 0,
            'structuring' => 0,
            'geographic' => 0,
            'amount' => 0,
        ];

        $result = $method->invoke($this->service, $customer, $scores);

        $this->assertContains('PEP customer', $result);
    }

    public function test_extract_risk_factors_excludes_pep_when_not_pep(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractRiskFactors');
        $method->setAccessible(true);

        $customer = new Customer;
        $customer->pep_status = false;

        $scores = [
            'velocity' => 0,
            'structuring' => 0,
            'geographic' => 0,
            'amount' => 0,
        ];

        $result = $method->invoke($this->service, $customer, $scores);

        $this->assertNotContains('PEP customer', $result);
    }

    public function test_threshold_service_integration(): void
    {
        $this->assertEquals('50000', $this->thresholdService->getRiskHighThreshold());
        $this->assertEquals('30000', $this->thresholdService->getRiskMediumThreshold());
        $this->assertEquals('10000', $this->thresholdService->getRiskLowThreshold());
        $this->assertEquals('3000', $this->thresholdService->getStructuringSubThreshold());
    }

    public function test_risk_tier_boundaries_are_consistent(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getRiskLevel');
        $method->setAccessible(true);

        // Score 78 should return "High" from both CustomerRiskScoringService and CustomerRiskProfile
        $scoringServiceResult = $method->invoke($this->service, 78);
        $profileResult = CustomerRiskProfile::getTierForScore(78);

        $this->assertEquals($profileResult, $scoringServiceResult,
            "Score 78 returned '{$scoringServiceResult}' from ScoringService but '{$profileResult}' from CustomerRiskProfile. They must be consistent."
        );

        // Test boundary consistency across all tiers
        $testCases = [
            ['score' => 85, 'expected' => 'Critical'],
            ['score' => 80, 'expected' => 'Critical'],
            ['score' => 79, 'expected' => 'High'],
            ['score' => 65, 'expected' => 'High'],
            ['score' => 60, 'expected' => 'High'],
            ['score' => 59, 'expected' => 'Medium'],
            ['score' => 35, 'expected' => 'Medium'],
            ['score' => 30, 'expected' => 'Medium'],
            ['score' => 29, 'expected' => 'Low'],
            ['score' => 0, 'expected' => 'Low'],
        ];

        foreach ($testCases as $case) {
            $scoringServiceTier = $method->invoke($this->service, $case['score']);
            $profileTier = CustomerRiskProfile::getTierForScore($case['score']);

            $this->assertEquals($case['expected'], $scoringServiceTier,
                "ScoringService: Score {$case['score']} expected '{$case['expected']}' but got '{$scoringServiceTier}'");
            $this->assertEquals($case['expected'], $profileTier,
                "CustomerRiskProfile: Score {$case['score']} expected '{$case['expected']}' but got '{$profileTier}'");
            $this->assertEquals($scoringServiceTier, $profileTier,
                "Score {$case['score']}: ScoringService returned '{$scoringServiceTier}' but Profile returned '{$profileTier}'");
        }
    }
}
