<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;
use App\Services\DTOs\ComplianceCheckResult;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected ComplianceService $complianceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
        $this->complianceService = resolve(ComplianceService::class);
    }

    #[Test]
    public function simplified_cdd_for_small_amounts(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => 'Low',
        ]);

        $amount = '2999.99';

        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);

        $this->assertEquals(CddLevel::Simplified, $cddLevel);
    }

    #[Test]
    public function standard_cdd_for_medium_amounts(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => 'Low',
        ]);

        $amount = '30000.00';

        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);

        $this->assertEquals(CddLevel::Standard, $cddLevel);
    }

    #[Test]
    public function enhanced_cdd_not_triggered_by_amount_alone(): void
    {
        // Large amount by low-risk, non-PEP, non-sanctioned customer should NOT trigger Enhanced
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => 'Low',
        ]);

        $amount = '100000.00'; // Large amount

        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);

        // Should be Standard (high amount but no risk factors), NOT Enhanced
        $this->assertEquals(CddLevel::Standard, $cddLevel);
    }

    #[Test]
    public function enhanced_cdd_triggered_by_high_risk_customer(): void
    {
        // Small amount by high-risk customer SHOULD trigger Enhanced
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => 'High',
        ]);

        $amount = '1000.00'; // Small amount

        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);

        $this->assertEquals(CddLevel::Enhanced, $cddLevel);
    }

    #[Test]
    public function enhanced_cdd_for_pep(): void
    {
        $amount = '1000.00'; // Small amount but PEP triggers enhanced

        $customer = Customer::factory()->create([
            'pep_status' => true,
            'sanction_hit' => false,
            'risk_rating' => 'Low',
        ]);

        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);

        $this->assertEquals(CddLevel::Enhanced, $cddLevel);
    }

    #[Test]
    public function enhanced_cdd_for_sanction_match(): void
    {
        $amount = '1000.00';

        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => true,
            'risk_rating' => 'Low',
        ]);

        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);

        $this->assertEquals(CddLevel::Enhanced, $cddLevel);
    }

    #[Test]
    public function velocity_check_uses_velocity_threshold_not_large_transaction(): void
    {
        $customer = Customer::factory()->create();

        // Create existing transactions totaling 40000 in last 24 hours
        Transaction::factory()
            ->for($customer)
            ->create([
                'amount_local' => '40000.0000',
                'created_at' => now()->subHours(12),
                'status' => TransactionStatus::Completed,
            ]);

        // Add a new transaction of 5000, bringing total to 45000
        // This should NOT exceed velocity alert threshold (50000)
        // But would exceed large transaction threshold (50000) if same threshold was used
        $result = $this->complianceService->checkVelocity($customer->id, '5000.0000');

        // Velocity threshold is 50000, total of 45000 should NOT exceed it
        $this->assertFalse($result['threshold_exceeded']);
        $this->assertEquals('45000.0000', $result['with_new_transaction']);
        $this->assertEquals('50000', $result['threshold_amount']);

        // Now test that 10000 more (total 50000) DOES trigger velocity check
        $result2 = $this->complianceService->checkVelocity($customer->id, '10000.0000');

        $this->assertTrue($result2['threshold_exceeded']);
        $this->assertEquals('50000', $result2['threshold_amount']);
    }

    #[Test]
    public function requires_hold_returns_compliance_check_result_dto(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);

        $result = $this->complianceService->requiresHold('1000.00', $customer);

        $this->assertInstanceOf(ComplianceCheckResult::class, $result);
    }

    #[Test]
    public function requires_hold_is_false_for_low_risk_small_amount(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);

        $result = $this->complianceService->requiresHold('1000.00', $customer);

        $this->assertFalse($result->requiresHold);
        $this->assertEmpty($result->reasons);
    }

    #[Test]
    public function requires_hold_is_true_for_large_amount(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'Low',
        ]);

        $result = $this->complianceService->requiresHold('50000.00', $customer);

        $this->assertTrue($result->requiresHold);
        $this->assertContains(ComplianceFlagType::EddRequired->value, $result->reasons);
    }

    #[Test]
    public function requires_hold_is_true_for_pep_customer(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'risk_rating' => 'Low',
        ]);

        $result = $this->complianceService->requiresHold('1000.00', $customer);

        $this->assertTrue($result->requiresHold);
        $this->assertContains(ComplianceFlagType::PepStatus->value, $result->reasons);
    }

    #[Test]
    public function requires_hold_is_true_for_high_risk_customer(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => 'High',
        ]);

        $result = $this->complianceService->requiresHold('1000.00', $customer);

        $this->assertTrue($result->requiresHold);
        $this->assertContains(ComplianceFlagType::HighRiskCustomer->value, $result->reasons);
    }

    #[Test]
    public function requires_hold_collects_multiple_reasons(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'risk_rating' => 'High',
        ]);

        $result = $this->complianceService->requiresHold('50000.00', $customer);

        $this->assertTrue($result->requiresHold);
        $this->assertContains(ComplianceFlagType::EddRequired->value, $result->reasons);
        $this->assertContains(ComplianceFlagType::PepStatus->value, $result->reasons);
        $this->assertContains(ComplianceFlagType::HighRiskCustomer->value, $result->reasons);
    }
}
