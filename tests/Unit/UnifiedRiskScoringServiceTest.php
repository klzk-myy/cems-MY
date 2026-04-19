<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\ThresholdService;
use App\Services\UnifiedRiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedRiskScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UnifiedRiskScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $mathService = new MathService;
        $thresholdService = new ThresholdService;
        $this->service = new UnifiedRiskScoringService($mathService, $thresholdService);
    }

    public function test_calculate_risk_score_returns_expected_structure(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'nationality' => 'Malaysia',
        ]);

        $result = $this->service->calculateRiskScore($customer);

        $this->assertArrayHasKey('total_score', $result);
        $this->assertArrayHasKey('risk_tier', $result);
        $this->assertArrayHasKey('factors', $result);
        $this->assertArrayHasKey('cdd_level', $result);
        $this->assertArrayHasKey('edd_required', $result);
        $this->assertIsInt($result['total_score']);
        $this->assertIsString($result['risk_tier']);
    }

    public function test_calculate_risk_score_for_pep_customer(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => true,
            'nationality' => 'Malaysia',
        ]);

        $result = $this->service->calculateRiskScore($customer);

        $this->assertEquals(100, $result['factors']['pep']);
        $this->assertTrue($result['edd_required']);
        $this->assertEquals(CddLevel::Enhanced, $result['cdd_level']);
    }

    public function test_calculate_risk_score_for_sanctioned_customer(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'sanction_hit' => true,
        ]);

        $result = $this->service->calculateRiskScore($customer);

        $this->assertEquals('Critical', $result['risk_tier']);
        $this->assertEquals(100, $result['factors']['sanctions']);
    }

    public function test_calculate_risk_score_with_transactions(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'nationality' => 'Malaysia',
        ]);

        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'amount_local' => 40000,
            'status' => 'Completed',
            'created_at' => now()->subDays(1),
        ]);

        $result = $this->service->calculateRiskScore($customer);

        $this->assertArrayHasKey('velocity', $result['factors']);
        $this->assertArrayHasKey('amount', $result['factors']);
    }

    public function test_get_factor_weights_returns_correct_structure(): void
    {
        $weights = $this->service->getFactorWeights();

        $this->assertIsArray($weights);
        $this->assertArrayHasKey('velocity', $weights);
        $this->assertArrayHasKey('structuring', $weights);
        $this->assertArrayHasKey('sanctions', $weights);
        $this->assertArrayHasKey('pep', $weights);
        $this->assertEquals(100, $weights['sanctions']);
    }

    public function test_set_factor_weights_updates_weights(): void
    {
        $newWeights = ['velocity' => 30, 'structuring' => 30];
        $this->service->setFactorWeights($newWeights);

        $weights = $this->service->getFactorWeights();

        $this->assertEquals(30, $weights['velocity']);
        $this->assertEquals(30, $weights['structuring']);
    }

    public function test_risk_tiers_are_determined_correctly(): void
    {
        $customer = Customer::factory()->create([
            'pep_status' => false,
            'nationality' => 'Malaysia',
        ]);

        $result = $this->service->calculateRiskScore($customer);

        $validTiers = ['Low', 'Medium', 'High', 'Critical'];
        $this->assertContains($result['risk_tier'], $validTiers);
    }

    public function test_cdd_levels_are_valid_enums(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->calculateRiskScore($customer);

        $this->assertInstanceOf(CddLevel::class, $result['cdd_level']);
    }
}
