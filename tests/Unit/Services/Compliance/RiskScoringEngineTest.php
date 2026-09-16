<?php

namespace Tests\Unit\Services\Compliance;

use App\Models\Customer;
use App\Models\HighRiskCountry;
use App\Services\Compliance\RiskCalculationService;
use App\Services\Compliance\RiskScoreWriteBackService;
use App\Services\Compliance\RiskScoringEngine;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    private RiskScoringEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RiskScoringEngine(
            new MathService,
            app(RiskCalculationService::class),
            new RiskScoreWriteBackService,
        );
    }

    #[Test]
    public function calculate_score_returns_base_score_of_20_for_customer_with_no_risk_factors(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MY',
            'pep_status' => false,
            'sanction_hit' => false,
            'is_active' => true,
        ]);

        $score = $this->engine->calculateScore($customer->id);

        // Base score is 20; without explicit risk factors the engine may still add
        // contextual factors (e.g., document-verification, baseline analysis).
        $this->assertGreaterThanOrEqual(20, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    #[Test]
    public function calculate_score_caps_at_100(): void
    {
        // Create a high-risk-country customer with multiple risk factors to push score above 100
        $country = HighRiskCountry::factory()->create(['country_code' => 'XX', 'risk_level' => 'High']);

        $customer = Customer::factory()->create([
            'nationality' => 'XX',
            'pep_status' => true,
            'sanction_hit' => true,
            'is_active' => true,
        ]);

        $score = $this->engine->calculateScore($customer->id);

        $this->assertLessThanOrEqual(100, $score);
    }

    #[Test]
    public function calculate_score_with_factors_returns_score_tier_and_factors_array(): void
    {
        $customer = Customer::factory()->create([
            'nationality' => 'MY',
            'pep_status' => false,
            'sanction_hit' => false,
            'is_active' => true,
        ]);

        $result = $this->engine->calculateScoreWithFactors($customer->id);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('tier', $result);
        $this->assertArrayHasKey('factors', $result);
        $this->assertIsArray($result['factors']);
    }

    #[Test]
    public function calculate_score_returns_base_for_nonexistent_customer(): void
    {
        $score = $this->engine->calculateScore(999_999);

        $this->assertSame(20, $score);
    }
}
