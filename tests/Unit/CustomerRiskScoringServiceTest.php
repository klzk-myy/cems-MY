<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CustomerRiskScoringService;
use App\Services\EncryptionService;
use App\Services\MathService;
use App\Services\ThresholdService;
use App\Services\CustomerScreeningService;
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

        $screeningService = $this->createMock(CustomerScreeningService::class);

        $this->service = new CustomerRiskScoringService(
            $screeningService,
            $complianceService,
            $auditService,
            $this->mathService,
            $this->thresholdService
        );
    }

    public function test_calculate_velocity_score_with_no_transactions(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, new Collection);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_velocity_score_with_small_transactions(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $transaction = new Transaction;
        $transaction->amount_local = '1000';
        $transaction->created_at = now();

        $result = $method->invoke($this->service, new Collection([$transaction]));

        $this->assertEquals(0, $result);
    }

    public function test_calculate_velocity_score_with_medium_transactions(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $transaction = new Transaction;
        $transaction->amount_local = '15000';
        $transaction->created_at = now();

        $result = $method->invoke($this->service, new Collection([$transaction]));

        $this->assertGreaterThanOrEqual(10, $result);
    }

    public function test_calculate_velocity_score_with_high_transactions(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $transaction = new Transaction;
        $transaction->amount_local = '60000';
        $transaction->created_at = now();

        $result = $method->invoke($this->service, new Collection([$transaction]));

        $this->assertGreaterThanOrEqual(30, $result);
    }

    public function test_calculate_velocity_score_max_is_40(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateVelocityScore');
        $method->setAccessible(true);

        $transactions = [];
        for ($i = 0; $i < 10; $i++) {
            $transaction = new Transaction;
            $transaction->amount_local = '60000';
            $transaction->created_at = now()->addMinutes($i);
            $transactions[] = $transaction;
        }

        $result = $method->invoke($this->service, new Collection($transactions));

        $this->assertLessThanOrEqual(40, $result);
    }

    public function test_calculate_structuring_score_with_no_transactions(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, new Collection);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_structuring_score_with_single_transaction(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $transaction = new Transaction;
        $transaction->amount_local = '2000';
        $transaction->created_at = now();

        $result = $method->invoke($this->service, new Collection([$transaction]));

        $this->assertEquals(0, $result);
    }

    public function test_calculate_structuring_score_with_three_transactions_same_hour(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $transactions = [];
        for ($i = 0; $i < 3; $i++) {
            $transaction = new Transaction;
            $transaction->amount_local = '2000';
            $transaction->created_at = now()->addMinutes($i);
            $transactions[] = $transaction;
        }

        $result = $method->invoke($this->service, new Collection($transactions));

        $this->assertGreaterThanOrEqual(25, $result);
    }

    public function test_calculate_structuring_score_max_is_30(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateStructuringScore');
        $method->setAccessible(true);

        $transactions = [];
        for ($i = 0; $i < 10; $i++) {
            $transaction = new Transaction;
            $transaction->amount_local = '2000';
            $transaction->created_at = now()->addMinutes($i);
            $transactions[] = $transaction;
        }

        $result = $method->invoke($this->service, new Collection($transactions));

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

    public function test_threshold_service_integration(): void
    {
        $this->assertEquals('50000', $this->thresholdService->getRiskHighThreshold());
        $this->assertEquals('30000', $this->thresholdService->getRiskMediumThreshold());
        $this->assertEquals('10000', $this->thresholdService->getRiskLowThreshold());
        $this->assertEquals('3000', $this->thresholdService->getStructuringSubThreshold());
    }
}
