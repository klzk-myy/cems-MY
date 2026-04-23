<?php

namespace Tests\Unit;

use App\Services\ThresholdService;
use Tests\TestCase;

class ThresholdServiceTest extends TestCase
{
    private ThresholdService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ThresholdService;
    }

    public function test_get_auto_approve_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getAutoApproveThreshold());
    }

    public function test_get_manager_approval_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getManagerApprovalThreshold());
    }

    public function test_get_standard_cdd_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getStandardCddThreshold());
    }

    public function test_get_large_transaction_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getLargeTransactionThreshold());
    }

    public function test_get_ctos_threshold(): void
    {
        $this->assertEquals('25000', $this->service->getCtosThreshold());
    }

    public function test_get_ctr_threshold(): void
    {
        $this->assertEquals('25000', $this->service->getCtrThreshold());
    }

    public function test_get_str_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getStrThreshold());
    }

    public function test_get_edd_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getEddThreshold());
    }

    public function test_get_risk_high_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getRiskHighThreshold());
    }

    public function test_get_risk_medium_threshold(): void
    {
        $this->assertEquals('30000', $this->service->getRiskMediumThreshold());
    }

    public function test_get_risk_low_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getRiskLowThreshold());
    }

    public function test_get_alert_critical_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getAlertCriticalThreshold());
    }

    public function test_get_alert_high_threshold(): void
    {
        $this->assertEquals('30000', $this->service->getAlertHighThreshold());
    }

    public function test_get_alert_medium_threshold(): void
    {
        $this->assertEquals('10000', $this->service->getAlertMediumThreshold());
    }

    public function test_get_variance_yellow_threshold(): void
    {
        $this->assertEquals('100.00', $this->service->getVarianceYellowThreshold());
    }

    public function test_get_variance_red_threshold(): void
    {
        $this->assertEquals('500.00', $this->service->getVarianceRedThreshold());
    }

    public function test_get_structuring_sub_threshold(): void
    {
        $this->assertEquals('3000', $this->service->getStructuringSubThreshold());
    }

    public function test_get_structuring_min_transactions(): void
    {
        $this->assertEquals(3, $this->service->getStructuringMinTransactions());
    }

    public function test_get_duration_warning_hours(): void
    {
        $this->assertEquals(24, $this->service->getDurationWarningHours());
    }

    public function test_get_duration_critical_hours(): void
    {
        $this->assertEquals(48, $this->service->getDurationCriticalHours());
    }

    public function test_get_velocity_alert_threshold(): void
    {
        $this->assertEquals('50000', $this->service->getVelocityAlertThreshold());
    }

    public function test_all_amount_thresholds_return_string(): void
    {
        $amountMethods = [
            'getAutoApproveThreshold',
            'getManagerApprovalThreshold',
            'getStandardCddThreshold',
            'getLargeTransactionThreshold',
            'getCtosThreshold',
            'getCtrThreshold',
            'getStrThreshold',
            'getEddThreshold',
            'getRiskHighThreshold',
            'getRiskMediumThreshold',
            'getRiskLowThreshold',
            'getAlertCriticalThreshold',
            'getAlertHighThreshold',
            'getAlertMediumThreshold',
            'getVarianceYellowThreshold',
            'getVarianceRedThreshold',
            'getStructuringSubThreshold',
            'getVelocityAlertThreshold',
        ];

        foreach ($amountMethods as $method) {
            $value = $this->service->$method();
            $this->assertIsString($value, "{$method} should return string");
        }
    }

    public function test_all_count_thresholds_return_int(): void
    {
        $countMethods = [
            'getStructuringMinTransactions',
            'getStructuringHourlyWindow',
            'getStructuringLookupDays',
            'getDurationWarningHours',
            'getDurationCriticalHours',
            'getVelocityWindowDays',
        ];

        foreach ($countMethods as $method) {
            $value = $this->service->$method();
            $this->assertIsInt($value, "{$method} should return int");
        }
    }
}
