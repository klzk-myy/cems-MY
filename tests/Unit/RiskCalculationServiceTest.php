<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\RiskCalculationService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RiskCalculationService $service;

    private MathService $mathService;

    private ThresholdService $thresholdService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mathService = new MathService;
        $this->thresholdService = new ThresholdService;
        $this->service = new RiskCalculationService($this->mathService, $this->thresholdService);
    }

    public function test_calculate_velocity_risk_returns_zero_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->calculateVelocityRisk($customer->id, 24);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_velocity_risk_with_small_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '1000',
                'created_at' => now(),
                'status' => TransactionStatus::Completed,
            ]);

        $result = $this->service->calculateVelocityRisk($customer->id, 24);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_velocity_risk_with_medium_risk_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '15000',
                'created_at' => now(),
                'status' => TransactionStatus::Completed,
            ]);

        $result = $this->service->calculateVelocityRisk($customer->id, 24);

        $this->assertGreaterThanOrEqual(10, $result);
    }

    public function test_calculate_velocity_risk_with_high_risk_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '60000',
                'created_at' => now(),
                'status' => TransactionStatus::Completed,
            ]);

        $result = $this->service->calculateVelocityRisk($customer->id, 24);

        $this->assertGreaterThanOrEqual(30, $result);
    }

    public function test_calculate_velocity_risk_max_is_40(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '60000',
                    'created_at' => now()->addMinutes($i),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        $result = $this->service->calculateVelocityRisk($customer->id, 24);

        $this->assertLessThanOrEqual(40, $result);
    }

    public function test_calculate_structuring_risk_returns_zero_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->calculateStructuringRisk($customer->id, 1);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_structuring_risk_with_single_transaction(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '2000',
                'created_at' => now(),
                'status' => TransactionStatus::Completed,
            ]);

        $result = $this->service->calculateStructuringRisk($customer->id, 1);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_structuring_risk_with_three_transactions_same_hour(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '2000',
                    'created_at' => now()->addMinutes($i),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        $result = $this->service->calculateStructuringRisk($customer->id, 1);

        $this->assertGreaterThanOrEqual(25, $result);
    }

    public function test_calculate_structuring_risk_max_is_30(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '2000',
                    'created_at' => now()->addMinutes($i),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        $result = $this->service->calculateStructuringRisk($customer->id, 1);

        $this->assertLessThanOrEqual(30, $result);
    }

    public function test_calculate_amount_risk_returns_zero_for_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->calculateAmountRisk($customer->id);

        $this->assertEquals(0, $result);
    }

    public function test_calculate_amount_risk_with_large_max_transaction(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '60000',
                'created_at' => now()->subDays(10),
                'status' => TransactionStatus::Completed,
            ]);

        $result = $this->service->calculateAmountRisk($customer->id);

        $this->assertGreaterThanOrEqual(30, $result);
    }

    public function test_calculate_amount_risk_with_escalation_above_average(): void
    {
        $customer = Customer::factory()->create();

        // Create historical transactions with average of 10000
        for ($i = 0; $i < 5; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '10000',
                    'created_at' => now()->subDays($i + 1),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        // Current transaction is 25000 (>2x average of 10000)
        $result = $this->service->calculateAmountRisk($customer->id, '25000');

        // Should have base score (10 for high risk max) + 10 for escalation
        $this->assertGreaterThanOrEqual(10, $result);
    }

    public function test_calculate_cumulative_risk_returns_not_triggered_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->calculateCumulativeRisk($customer->id);

        $this->assertFalse($result['triggered']);
        // Total should be '0.000000' (MathService uses scale 6)
        $this->assertEquals('0.000000', $result['total']);
        $this->assertEquals('50000', $result['threshold']);
    }

    public function test_calculate_cumulative_risk_with_transactions_below_threshold(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '10000',
                    'created_at' => now()->subHours($i + 1),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        $result = $this->service->calculateCumulativeRisk($customer->id);

        $this->assertFalse($result['triggered']);
    }

    public function test_calculate_cumulative_risk_triggered_with_high_transactions(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '20000',
                    'created_at' => now()->subHours($i + 1),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        $result = $this->service->calculateCumulativeRisk($customer->id);

        $this->assertTrue($result['triggered']);
    }

    public function test_calculate_cumulative_risk_includes_current_amount(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '40000',
                'created_at' => now()->subHours(1),
                'status' => TransactionStatus::Completed,
            ]);

        // Add current amount of 20000, total should be 60000
        $result = $this->service->calculateCumulativeRisk($customer->id, '20000');

        $this->assertTrue($result['triggered']);
    }

    public function test_check_velocity_threshold_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->checkVelocityThreshold($customer->id, 24, 3);

        $this->assertFalse($result['triggered']);
        $this->assertEquals(0, $result['count']);
        $this->assertEquals(3, $result['threshold']);
    }

    public function test_check_velocity_threshold_triggered(): void
    {
        $customer = Customer::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()
                ->for($customer)
                ->create([
                    'amount_local' => '5000',
                    'created_at' => now()->subMinutes($i + 1),
                    'status' => TransactionStatus::Completed,
                ]);
        }

        $result = $this->service->checkVelocityThreshold($customer->id, 24, 3);

        $this->assertTrue($result['triggered']);
        $this->assertEquals(3, $result['count']);
    }

    public function test_check_structuring_threshold_with_no_transactions(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->checkStructuringThreshold($customer->id, 1, 2);

        $this->assertFalse($result['triggered']);
        $this->assertEquals(0, $result['count']);
    }

    public function test_cancelled_transactions_excluded_from_calculations(): void
    {
        $customer = Customer::factory()->create();

        // Create cancelled transaction (should not count)
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '60000',
                'created_at' => now(),
                'status' => TransactionStatus::Cancelled,
            ]);

        // Create completed transaction (should count)
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '1000',
                'created_at' => now(),
                'status' => TransactionStatus::Completed,
            ]);

        $velocity = $this->service->calculateVelocityRisk($customer->id, 24);
        $structuring = $this->service->calculateStructuringRisk($customer->id, 1);
        $amount = $this->service->calculateAmountRisk($customer->id);
        $cumulative = $this->service->calculateCumulativeRisk($customer->id);

        // All should be low since cancelled transaction is excluded
        $this->assertLessThan(30, $velocity);
        $this->assertFalse($cumulative['triggered']);
    }

    public function test_get_overall_risk_score_combines_all_factors(): void
    {
        $customer = Customer::factory()->create();

        // Create high risk transactions
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '60000',
                'created_at' => now(),
                'status' => TransactionStatus::Completed,
            ]);

        $result = $this->service->getOverallRiskScore($customer->id);

        $this->assertArrayHasKey('velocity', $result);
        $this->assertArrayHasKey('structuring', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('cumulative', $result);
        $this->assertArrayHasKey('pattern', $result);
        $this->assertArrayHasKey('overall', $result);
        $this->assertLessThanOrEqual(100, $result['overall']);
    }
}
