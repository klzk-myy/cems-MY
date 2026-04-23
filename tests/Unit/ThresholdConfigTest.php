<?php

namespace Tests\Unit;

use Tests\TestCase;

class ThresholdConfigTest extends TestCase
{
    public function test_config_file_exists(): void
    {
        $config = config('thresholds');
        $this->assertNotNull($config);
    }

    public function test_approval_thresholds_exist(): void
    {
        $this->assertEquals('10000', config('thresholds.approval.auto_approve'));
        $this->assertEquals('50000', config('thresholds.approval.manager'));
    }

    public function test_cdd_thresholds_exist(): void
    {
        $this->assertEquals('3000', config('thresholds.cdd.specific'));
        $this->assertEquals('10000', config('thresholds.cdd.standard'));
        $this->assertEquals('50000', config('thresholds.cdd.large_transaction'));
    }

    public function test_risk_scoring_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.risk_scoring.high'));
        $this->assertEquals('30000', config('thresholds.risk_scoring.medium'));
        $this->assertEquals('10000', config('thresholds.risk_scoring.low'));
    }

    public function test_alert_triage_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.alert_triage.critical'));
        $this->assertEquals('30000', config('thresholds.alert_triage.high'));
        $this->assertEquals('10000', config('thresholds.alert_triage.medium'));
    }

    public function test_reporting_thresholds_exist(): void
    {
        $this->assertEquals('25000', config('thresholds.reporting.ctos'));
        $this->assertEquals('25000', config('thresholds.reporting.ctr'));
        $this->assertEquals('50000', config('thresholds.reporting.str'));
        $this->assertEquals('50000', config('thresholds.reporting.edd'));
        $this->assertEquals('25000', config('thresholds.reporting.lctr'));
    }

    public function test_structuring_thresholds_exist(): void
    {
        $this->assertEquals('3000', config('thresholds.structuring.sub_threshold'));
        $this->assertEquals(3, config('thresholds.structuring.min_transactions'));
        $this->assertEquals(1, config('thresholds.structuring.hourly_window'));
        $this->assertEquals(7, config('thresholds.structuring.lookup_days'));
    }

    public function test_duration_thresholds_exist(): void
    {
        $this->assertEquals(24, config('thresholds.duration.warning_hours'));
        $this->assertEquals(48, config('thresholds.duration.critical_hours'));
    }

    public function test_variance_thresholds_exist(): void
    {
        $this->assertEquals('100.00', config('thresholds.variance.yellow'));
        $this->assertEquals('500.00', config('thresholds.variance.red'));
    }

    public function test_velocity_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.velocity.alert_threshold'));
        $this->assertEquals('45000', config('thresholds.velocity.warning_threshold'));
        $this->assertEquals(90, config('thresholds.velocity.window_days'));
    }

    public function test_aml_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.aml.amount_threshold'));
        $this->assertEquals('50000', config('thresholds.aml.aggregate_threshold'));
    }

    public function test_all_threshold_values_are_string_or_int(): void
    {
        $thresholds = config('thresholds');
        foreach ($thresholds as $category => $values) {
            foreach ($values as $key => $value) {
                $this->assertTrue(
                    is_string($value) || is_int($value),
                    "Threshold thresholds.{$category}.{$key} should be string or int, got ".gettype($value)
                );
            }
        }
    }
}
