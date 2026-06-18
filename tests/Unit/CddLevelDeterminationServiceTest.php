<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Enums\PepType;
use App\Enums\RiskRating;
use App\Models\Customer;
use App\Services\Compliance\CddLevelDeterminationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CddLevelDeterminationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CddLevelDeterminationService $service;

    protected MathService $mathService;

    protected ThresholdService $thresholdService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
        $this->thresholdService = new ThresholdService;
        $this->service = new CddLevelDeterminationService(
            $this->mathService,
            $this->thresholdService
        );
    }

    #[Test]
    public function simplified_cdd_for_small_amounts(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => RiskRating::Low,
        ]);

        $level = $this->service->determineCDDLevel('2999.99', $customer);

        $this->assertEquals(CddLevel::Simplified, $level);
    }

    #[Test]
    public function standard_cdd_for_large_amounts_non_pep(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => RiskRating::Low,
        ]);

        $level = $this->service->determineCDDLevel('30000.00', $customer);

        $this->assertEquals(CddLevel::Standard, $level);
    }

    #[Test]
    public function foreign_pep_always_gets_enhanced_cdd(): void
    {
        // Foreign PEPs per pd-00.md 15.2 require Enhanced CDD always
        // Even with low risk rating and small transaction amount
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::Low,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00', // Below RM 10,000 threshold
            $customer,
            PepType::Foreign->value
        );

        $this->assertEquals(CddLevel::Enhanced, $level);
        $this->assertEquals(['Foreign PEP'], $this->service->getLastCddTriggers());
    }

    #[Test]
    public function domestic_pep_low_risk_specific_cdd(): void
    {
        // Domestic PEPs per pd-00.md 15.3 only get Enhanced CDD if higher risk
        // Low risk domestic PEP should NOT get Enhanced for transaction >= RM 3000
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::Low,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00', // >= RM 3000, so Specific CDD
            $customer,
            PepType::Domestic->value
        );

        // Should be Specific (amount-based) since domestic PEP is low risk and amount >= 3000
        $this->assertEquals(CddLevel::Specific, $level);
    }

    #[Test]
    public function domestic_pep_high_risk_gets_enhanced(): void
    {
        // Domestic PEP with high risk rating should get Enhanced CDD
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::High,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00',
            $customer,
            PepType::Domestic->value
        );

        $this->assertEquals(CddLevel::Enhanced, $level);
        $this->assertEquals(['Domestic PEP (higher risk)'], $this->service->getLastCddTriggers());
    }

    #[Test]
    public function domestic_pep_medium_risk_gets_enhanced(): void
    {
        // Domestic PEP with medium risk rating should get Enhanced CDD (per isHigherRisk logic)
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::Medium,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00',
            $customer,
            PepType::Domestic->value
        );

        $this->assertEquals(CddLevel::Enhanced, $level);
    }

    #[Test]
    public function legacy_pep_status_without_type_still_works(): void
    {
        // Legacy PEP status without type distinction should still trigger Enhanced
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'risk_rating' => RiskRating::Low,
        ]);

        // No pepType provided (null) - should fall back to legacy behavior
        $level = $this->service->determineCDDLevel('5000.00', $customer);

        $this->assertEquals(CddLevel::Enhanced, $level);
    }

    #[Test]
    public function family_member_pep_low_risk_specific_cdd(): void
    {
        // Family member of PEP with low risk should not get automatic Enhanced
        // Amount >= RM 3000 triggers Specific CDD (amount-based)
        $customer = Customer::factory()->create([
            'pep_status' => false, // Explicitly not a PEP
            'risk_rating' => RiskRating::Low,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00', // >= RM 3000, so Specific CDD
            $customer,
            PepType::FamilyMember->value
        );

        // Should be Specific since low risk and amount >= 3000
        $this->assertEquals(CddLevel::Specific, $level);
    }

    #[Test]
    public function close_associate_pep_high_risk_gets_enhanced(): void
    {
        // Close associate of PEP with high risk should get Enhanced
        // Note: pep_status is true since this person IS the close associate (they have PEP relation)
        $customer = Customer::factory()->create([
            'pep_status' => true, // This person IS the close associate
            'risk_rating' => RiskRating::High,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00',
            $customer,
            PepType::CloseAssociate->value
        );

        $this->assertEquals(CddLevel::Enhanced, $level);
    }

    #[Test]
    public function international_org_pep_high_risk_gets_enhanced(): void
    {
        // International org PEP with high risk should get Enhanced
        $customer = Customer::factory()->create([
            'risk_rating' => RiskRating::High,
        ]);

        $level = $this->service->determineCDDLevel(
            '5000.00',
            $customer,
            PepType::InternationalOrg->value
        );

        $this->assertEquals(CddLevel::Enhanced, $level);
    }

    #[Test]
    public function high_risk_customer_always_gets_enhanced(): void
    {
        // High risk customer (non-PEP) should get Enhanced CDD
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => false,
            'risk_rating' => RiskRating::High,
        ]);

        $level = $this->service->determineCDDLevel('5000.00', $customer);

        $this->assertEquals(CddLevel::Enhanced, $level);
        $this->assertEquals(['High risk customer'], $this->service->getLastCddTriggers());
    }

    #[Test]
    public function sanctions_match_always_gets_enhanced(): void
    {
        // Customer with sanctions match should get Enhanced CDD
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'risk_rating' => RiskRating::Low,
        ]);

        // Create service with a mock sanction check that returns true
        $service = new CddLevelDeterminationService(
            $this->mathService,
            $this->thresholdService,
            fn () => true // Always return true for sanctions match
        );

        $level = $service->determineCDDLevel('5000.00', $customer);

        $this->assertEquals(CddLevel::Enhanced, $level);
        $this->assertEquals(['Sanctions match'], $service->getLastCddTriggers());
    }
}
