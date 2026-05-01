<?php

namespace Tests\Unit;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Services\ComplianceService;
use App\Services\MathService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_simplified_cdd_for_small_amounts(): void
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

    public function test_standard_cdd_for_medium_amounts(): void
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

    public function test_enhanced_cdd_not_triggered_by_amount_alone(): void
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

    public function test_enhanced_cdd_triggered_by_high_risk_customer(): void
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

    public function test_enhanced_cdd_for_pep(): void
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

    public function test_enhanced_cdd_for_sanction_match(): void
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

    public function test_str_deadline_is_next_working_day(): void
    {
        // Monday suspicion should have Tuesday deadline
        $monday = Carbon::create(2026, 5, 4, 10, 0, 0); // May 4, 2026 is a Monday
        Carbon::setTestNow($monday);

        $result = $this->complianceService->calculateStrDeadline($monday);

        $this->assertEquals(1, $result['working_days_until_deadline']);
        $this->assertTrue($result['deadline']->isTuesday());
        $this->assertEquals(5, $result['deadline']->day); // Next day (Tuesday)

        Carbon::setTestNow(); // Reset
    }

    public function test_str_deadline_skips_weekend(): void
    {
        // Friday suspicion should have Monday deadline (skips weekend)
        $friday = Carbon::create(2026, 5, 8, 10, 0, 0); // May 8, 2026 is a Friday
        Carbon::setTestNow($friday);

        $result = $this->complianceService->calculateStrDeadline($friday);

        $this->assertEquals(1, $result['working_days_until_deadline']);
        $this->assertTrue($result['deadline']->isMonday());
        $this->assertEquals(11, $result['deadline']->day); // Following Monday (May 11)

        Carbon::setTestNow(); // Reset
    }
}
