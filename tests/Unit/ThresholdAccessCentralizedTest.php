<?php

namespace Tests\Unit;

use App\Enums\AmlRuleType;
use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Services\ThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThresholdAccessCentralizedTest extends TestCase
{
    use RefreshDatabase;

    public function test_cdd_level_uses_centralized_threshold_access(): void
    {
        // Verify CddLevel::determine uses centralized thresholds
        $standard = CddLevel::determine('10000');
        $this->assertEquals(CddLevel::Standard, $standard);

        $specific = CddLevel::determine('5000');
        $this->assertEquals(CddLevel::Specific, $specific);

        $simplified = CddLevel::determine('1000');
        $this->assertEquals(CddLevel::Simplified, $simplified);

        // Verify CddLevel::thresholdAmount() uses centralized thresholds
        $this->assertStringContainsString('3000', CddLevel::Specific->thresholdAmount());
        $this->assertStringContainsString('10000', CddLevel::Standard->thresholdAmount());
    }

    public function test_cdd_level_enhanced_bypasses_amount_check(): void
    {
        // Enhanced is triggered by risk, not amount
        $enhanced = CddLevel::determine('100', isPep: true);
        $this->assertEquals(CddLevel::Enhanced, $enhanced);

        $enhancedByRisk = CddLevel::determine('100', riskRating: 'High');
        $this->assertEquals(CddLevel::Enhanced, $enhancedByRisk);
    }

    public function test_compliance_flag_type_uses_centralized_threshold_access(): void
    {
        // Verify ComplianceFlagType::thresholdAmount() uses centralized thresholds
        $largeAmount = ComplianceFlagType::LargeAmount->thresholdAmount();
        $this->assertEquals('50000', $largeAmount);

        $eddRequired = ComplianceFlagType::EddRequired->thresholdAmount();
        $this->assertEquals('50000', $eddRequired);

        $velocity = ComplianceFlagType::Velocity->thresholdAmount();
        $this->assertEquals('50000', $velocity);

        // Flags without threshold return null
        $this->assertNull(ComplianceFlagType::SanctionsHit->thresholdAmount());
        $this->assertNull(ComplianceFlagType::ManualReview->thresholdAmount());
    }

    public function test_aml_rule_type_uses_centralized_threshold_access(): void
    {
        // Verify AmlRuleType::defaultConditions() uses centralized thresholds
        $structuringConditions = AmlRuleType::Structuring->defaultConditions();
        $this->assertEquals('50000', $structuringConditions['aggregate_threshold']);

        $amountThresholdConditions = AmlRuleType::AmountThreshold->defaultConditions();
        $this->assertEquals('50000', $amountThresholdConditions['min_amount']);
    }

    public function test_all_enum_threshold_access_routes_through_threshold_service(): void
    {
        // This test verifies that enum threshold access is routed through ThresholdService
        // by checking that the values match what ThresholdService provides

        $thresholdService = new ThresholdService;

        // CddLevel uses centralized thresholds in determine() method
        // Standard CDD is triggered at or above standard threshold
        $this->assertTrue(
            bccomp('10000', $thresholdService->getStandardCddThreshold()) >= 0,
            'Amount of 10000 should meet standard CDD threshold'
        );
        $this->assertTrue(
            bccomp('9999', $thresholdService->getStandardCddThreshold()) < 0,
            'Amount of 9999 should be below standard CDD threshold'
        );

        // ComplianceFlagType thresholds
        $this->assertEquals(
            $thresholdService->getLargeTransactionThreshold(),
            ComplianceFlagType::LargeAmount->thresholdAmount()
        );

        // AmlRuleType thresholds
        $structuringDefaults = AmlRuleType::Structuring->defaultConditions();
        $this->assertEquals(
            $thresholdService->getAmlAggregateThreshold(),
            $structuringDefaults['aggregate_threshold']
        );
    }

    public function test_threshold_service_provides_aml_thresholds(): void
    {
        $thresholdService = new ThresholdService;

        $this->assertEquals('50000', $thresholdService->getAmlAggregateThreshold());
        $this->assertEquals('50000', $thresholdService->getAmlAmountThreshold());
    }

    public function test_enum_thresholds_match_threshold_service_constants(): void
    {
        // Verify the fallback constants exist and match
        $thresholdService = new ThresholdService;

        $this->assertEquals('50000', $thresholdService->getAmlAggregateThreshold());
    }
}
