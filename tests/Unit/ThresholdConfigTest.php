<?php

namespace Tests\Unit;

use App\Support\ThresholdDefaults;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThresholdConfigTest extends TestCase
{
    #[Test]
    public function config_file_exists(): void
    {
        $config = config('thresholds');
        $this->assertNotNull($config);
    }

    #[Test]
    public function approval_thresholds_exist(): void
    {
        $this->assertEquals('10000', config('thresholds.approval.auto_approve'));
        $this->assertEquals('50000', config('thresholds.approval.manager'));
    }

    #[Test]
    public function cdd_thresholds_exist(): void
    {
        $this->assertEquals('3000', config('thresholds.cdd.specific'));
        $this->assertEquals('10000', config('thresholds.cdd.standard'));
        $this->assertEquals('50000', config('thresholds.cdd.large_transaction'));
    }

    #[Test]
    public function risk_scoring_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.risk_scoring.high'));
        $this->assertEquals('30000', config('thresholds.risk_scoring.medium'));
        $this->assertEquals('10000', config('thresholds.risk_scoring.low'));
    }

    #[Test]
    public function alert_triage_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.alert_triage.critical'));
        $this->assertEquals('30000', config('thresholds.alert_triage.high'));
        $this->assertEquals('10000', config('thresholds.alert_triage.medium'));
    }

    #[Test]
    public function reporting_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.reporting.str'));
        $this->assertEquals('50000', config('thresholds.reporting.edd'));
    }

    #[Test]
    public function structuring_thresholds_exist(): void
    {
        $this->assertEquals('3000', config('thresholds.structuring.sub_threshold'));
        $this->assertEquals(3, config('thresholds.structuring.min_transactions'));
        $this->assertEquals(1, config('thresholds.structuring.hourly_window'));
        $this->assertEquals(7, config('thresholds.structuring.lookup_days'));
    }

    #[Test]
    public function duration_thresholds_exist(): void
    {
        $this->assertEquals(24, config('thresholds.duration.warning_hours'));
        $this->assertEquals(48, config('thresholds.duration.critical_hours'));
    }

    #[Test]
    public function variance_thresholds_exist(): void
    {
        $this->assertEquals('100.00', config('thresholds.variance.yellow'));
        $this->assertEquals('500.00', config('thresholds.variance.red'));
    }

    #[Test]
    public function velocity_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.velocity.alert_threshold'));
        $this->assertEquals('45000', config('thresholds.velocity.warning_threshold'));
        $this->assertEquals(90, config('thresholds.velocity.window_days'));
    }

    #[Test]
    public function aml_thresholds_exist(): void
    {
        $this->assertEquals('50000', config('thresholds.aml.amount_threshold'));
        $this->assertEquals('50000', config('thresholds.aml.aggregate_threshold'));
    }

    #[Test]
    public function velocity_amount_window_hours_exists(): void
    {
        $this->assertEquals(24, config('thresholds.velocity.amount_window_hours'));
    }

    #[Test]
    public function geographic_risk_thresholds_exist(): void
    {
        $this->assertEquals(30, config('thresholds.geographic_risk.high_country_weight'));
        $this->assertEquals(15, config('thresholds.geographic_risk.recent_travel_weight'));
    }

    #[Test]
    public function position_limits_exist(): void
    {
        $limits = config('thresholds.position_limits');

        $this->assertCount(9, $limits);
        $this->assertEquals('1000000', $limits['usd']);
        $this->assertEquals('100000000', $limits['jpy']);
    }

    #[Test]
    public function config_defaults_match_service_fallback_constants(): void
    {
        $map = [
            'approval.auto_approve' => ThresholdDefaults::FALLBACK_AUTO_APPROVE,
            'approval.manager' => ThresholdDefaults::FALLBACK_MANAGER,
            'cdd.specific' => ThresholdDefaults::FALLBACK_CDD_SPECIFIC,
            'cdd.standard' => ThresholdDefaults::FALLBACK_CDD_STANDARD,
            'cdd.large_transaction' => ThresholdDefaults::FALLBACK_CDD_LARGE,
            'reporting.str' => ThresholdDefaults::FALLBACK_STR,
            'reporting.edd' => ThresholdDefaults::FALLBACK_EDD,
            'risk_scoring.high' => ThresholdDefaults::FALLBACK_RISK_HIGH,
            'risk_scoring.medium' => ThresholdDefaults::FALLBACK_RISK_MEDIUM,
            'risk_scoring.low' => ThresholdDefaults::FALLBACK_RISK_LOW,
            'alert_triage.critical' => ThresholdDefaults::FALLBACK_ALERT_CRITICAL,
            'alert_triage.high' => ThresholdDefaults::FALLBACK_ALERT_HIGH,
            'alert_triage.medium' => ThresholdDefaults::FALLBACK_ALERT_MEDIUM,
            'variance.yellow' => ThresholdDefaults::FALLBACK_VARIANCE_YELLOW,
            'variance.red' => ThresholdDefaults::FALLBACK_VARIANCE_RED,
            'structuring.sub_threshold' => ThresholdDefaults::FALLBACK_STRUCTURING_SUB,
            'structuring.min_transactions' => ThresholdDefaults::FALLBACK_STRUCTURING_MIN_TXNS,
            'structuring.hourly_window' => ThresholdDefaults::FALLBACK_STRUCTURING_HOURLY_WINDOW,
            'structuring.lookup_days' => ThresholdDefaults::FALLBACK_STRUCTURING_LOOKUP_DAYS,
            'duration.warning_hours' => ThresholdDefaults::FALLBACK_DURATION_WARNING,
            'duration.critical_hours' => ThresholdDefaults::FALLBACK_DURATION_CRITICAL,
            'velocity.alert_threshold' => ThresholdDefaults::FALLBACK_VELOCITY_ALERT,
            'velocity.warning_threshold' => ThresholdDefaults::FALLBACK_VELOCITY_WARNING,
            'velocity.window_days' => ThresholdDefaults::FALLBACK_VELOCITY_WINDOW_DAYS,
            'velocity.amount_window_hours' => ThresholdDefaults::FALLBACK_VELOCITY_AMOUNT_WINDOW_HOURS,
            'geographic_risk.high_country_weight' => ThresholdDefaults::FALLBACK_GEO_HIGH_COUNTRY_WEIGHT,
            'geographic_risk.recent_travel_weight' => ThresholdDefaults::FALLBACK_GEO_RECENT_TRAVEL_WEIGHT,
            'currency_flow.round_trip_threshold' => ThresholdDefaults::FALLBACK_ROUND_TRIP,
            'currency_flow.lookback_days' => ThresholdDefaults::FALLBACK_CURRENCY_FLOW_LOOKBACK_DAYS,
            'aml.amount_threshold' => ThresholdDefaults::FALLBACK_AML_AMOUNT,
            'aml.aggregate_threshold' => ThresholdDefaults::FALLBACK_AML_AGGREGATE,
            'rates.override_limit_teller' => ThresholdDefaults::FALLBACK_RATE_OVERRIDE_LIMIT_TELLER,
            'rates.override_limit_manager' => ThresholdDefaults::FALLBACK_RATE_OVERRIDE_LIMIT_MANAGER,
            'kyc.grace_period_days' => ThresholdDefaults::FALLBACK_KYC_GRACE_PERIOD_DAYS,
            'risk_review.batch_size' => ThresholdDefaults::FALLBACK_RISK_REVIEW_BATCH_SIZE,
            'performance.response_time_warning' => ThresholdDefaults::FALLBACK_RESPONSE_TIME_WARNING,
            'performance.cache_hit_rate_warning' => ThresholdDefaults::FALLBACK_CACHE_HIT_RATE_WARNING,
            'performance.query_time_warning' => ThresholdDefaults::FALLBACK_QUERY_TIME_WARNING,
            'performance.job_duration_warning' => ThresholdDefaults::FALLBACK_JOB_DURATION_WARNING,
        ];

        foreach ($map as $key => $constant) {
            $this->assertEquals(
                $constant,
                config("thresholds.{$key}"),
                "Config default for {$key} diverged from its ThresholdDefaults fallback"
            );
        }
    }

    #[Test]
    public function all_threshold_values_are_string_or_int(): void
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
