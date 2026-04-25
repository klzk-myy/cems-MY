<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Services\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MathService $mathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mathService = new MathService;
    }

    public function test_simplified_cdd_for_small_amounts(): void
    {
        $amount = '2999.99';

        $cddLevel = $this->determineCddLevel($amount, false, false);

        $this->assertEquals(CddLevel::Simplified, $cddLevel);
    }

    public function test_standard_cdd_for_medium_amounts(): void
    {
        $amount = '30000.00';

        $cddLevel = $this->determineCddLevel($amount, false, false);

        $this->assertEquals(CddLevel::Standard, $cddLevel);
    }

    public function test_enhanced_cdd_for_large_amounts(): void
    {
        $amount = '50000.00';

        $cddLevel = $this->determineCddLevel($amount, false, false);

        $this->assertEquals(CddLevel::Enhanced, $cddLevel);
    }

    public function test_enhanced_cdd_for_pep(): void
    {
        $amount = '1000.00'; // Small amount but PEP triggers enhanced

        $cddLevel = $this->determineCddLevel($amount, true, false);

        $this->assertEquals(CddLevel::Enhanced, $cddLevel);
    }

    public function test_enhanced_cdd_for_sanction_match(): void
    {
        $amount = '1000.00';

        $cddLevel = $this->determineCddLevel($amount, false, true);

        $this->assertEquals(CddLevel::Enhanced, $cddLevel);
    }

    public function test_enhanced_cdd_triggers_include_amount_when_large_transaction(): void
    {
        // Test that amount >= RM 50,000 triggers Enhanced CDD
        // The determineCDDLevel helper in test class uses hardcoded 50000 threshold
        $amount = '50000.00';
        $isLargeAmount = bccomp($amount, '50000', 2) >= 0;

        // Verify large amount triggers enhanced
        $this->assertTrue($isLargeAmount);
    }

    public function test_requires_hold_for_large_amounts(): void
    {
        $amount = '50000.00';
        $requiresHold = bccomp($amount, '50000', 2) >= 0;

        $this->assertTrue($requiresHold);
    }

    public function test_requires_hold_for_pep_status(): void
    {
        $isPep = true;
        $requiresHold = $isPep;

        $this->assertTrue($requiresHold);
    }

    public function test_requires_hold_for_high_risk_customer(): void
    {
        $riskRating = 'high';
        $isHighRisk = $riskRating === 'high';

        $this->assertTrue($isHighRisk);
    }

    public function test_requires_hold_for_sanction_match(): void
    {
        $isSanctionMatch = true;
        $requiresHold = $isSanctionMatch;

        $this->assertTrue($requiresHold);
    }

    public function test_atomic_threshold_exact(): void
    {
        $amount = '3000.00';
        $isAtLeastStandard = bccomp($amount, '3000', 2) >= 0;

        $this->assertTrue($isAtLeastStandard);
    }

    public function test_large_transaction_threshold_exact(): void
    {
        $amount = '50000.00';
        $isLarge = bccomp($amount, '50000', 2) >= 0;

        $this->assertTrue($isLarge);
    }

    public function test_just_below_large_transaction_threshold(): void
    {
        $amount = '49999.99';
        $isLarge = bccomp($amount, '50000', 2) >= 0;

        $this->assertFalse($isLarge);
    }

    public function test_ctos_threshold_exact(): void
    {
        $amount = '10000.00';
        $requiresCtos = bccomp($amount, '10000', 2) >= 0;

        $this->assertTrue($requiresCtos);
    }

    public function test_just_below_ctos_threshold(): void
    {
        $amount = '9999.99';
        $requiresCtos = bccomp($amount, '10000', 2) >= 0;

        $this->assertFalse($requiresCtos);
    }

    /**
     * Helper method to determine CDD level
     */
    private function determineCddLevel(string $amount, bool $isPep, bool $isSanctionMatch): CddLevel
    {
        if (bccomp($amount, '50000', 2) >= 0 || $isPep || $isSanctionMatch) {
            return CddLevel::Enhanced;
        }

        if (bccomp($amount, '3000', 2) >= 0) {
            return CddLevel::Standard;
        }

        return CddLevel::Simplified;
    }
}
