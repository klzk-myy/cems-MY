<?php

namespace App\Helpers;

use App\Services\ThresholdService;

/**
 * Trait for centralized threshold access in enums.
 *
 * Enums cannot use constructor dependency injection, so this trait
 * provides static methods that delegate to ThresholdService for
 * centralized threshold access with audit logging.
 */
trait Thresholdable
{
    /**
     * Get the threshold service instance (static accessor).
     *
     * Uses app() helper since enums cannot use constructor DI.
     */
    protected static function thresholdService(): ThresholdService
    {
        return app(ThresholdService::class);
    }

    /**
     * Get auto-approve threshold (approval.auto_approve).
     */
    protected static function getAutoApproveThreshold(): string
    {
        return self::thresholdService()->getAutoApproveThreshold();
    }

    /**
     * Get manager approval threshold (approval.manager).
     */
    protected static function getManagerApprovalThreshold(): string
    {
        return self::thresholdService()->getManagerApprovalThreshold();
    }

    /**
     * Get specific CDD threshold (cdd.specific).
     */
    protected static function getSpecificCddThreshold(): string
    {
        return self::thresholdService()->getSpecificCddThreshold();
    }

    /**
     * Get standard CDD threshold (cdd.standard).
     */
    protected static function getStandardCddThreshold(): string
    {
        return self::thresholdService()->getStandardCddThreshold();
    }

    /**
     * Get large transaction threshold (cdd.large_transaction).
     */
    protected static function getLargeTransactionThreshold(): string
    {
        return self::thresholdService()->getLargeTransactionThreshold();
    }

    /**
     * Get STR threshold (reporting.str).
     */
    protected static function getStrThreshold(): string
    {
        return self::thresholdService()->getStrThreshold();
    }

    /**
     * Get EDD threshold (reporting.edd).
     */
    protected static function getEddThreshold(): string
    {
        return self::thresholdService()->getEddThreshold();
    }

    /**
     * Get risk high threshold (risk_scoring.high).
     */
    protected static function getRiskHighThreshold(): string
    {
        return self::thresholdService()->getRiskHighThreshold();
    }

    /**
     * Get risk medium threshold (risk_scoring.medium).
     */
    protected static function getRiskMediumThreshold(): string
    {
        return self::thresholdService()->getRiskMediumThreshold();
    }

    /**
     * Get risk low threshold (risk_scoring.low).
     */
    protected static function getRiskLowThreshold(): string
    {
        return self::thresholdService()->getRiskLowThreshold();
    }

    /**
     * Get alert critical threshold (alert_triage.critical).
     */
    protected static function getAlertCriticalThreshold(): string
    {
        return self::thresholdService()->getAlertCriticalThreshold();
    }

    /**
     * Get alert high threshold (alert_triage.high).
     */
    protected static function getAlertHighThreshold(): string
    {
        return self::thresholdService()->getAlertHighThreshold();
    }

    /**
     * Get alert medium threshold (alert_triage.medium).
     */
    protected static function getAlertMediumThreshold(): string
    {
        return self::thresholdService()->getAlertMediumThreshold();
    }

    /**
     * Get variance yellow threshold (variance.yellow).
     */
    protected static function getVarianceYellowThreshold(): string
    {
        return self::thresholdService()->getVarianceYellowThreshold();
    }

    /**
     * Get variance red threshold (variance.red).
     */
    protected static function getVarianceRedThreshold(): string
    {
        return self::thresholdService()->getVarianceRedThreshold();
    }

    /**
     * Get structuring sub-threshold (structuring.sub_threshold).
     */
    protected static function getStructuringSubThreshold(): string
    {
        return self::thresholdService()->getStructuringSubThreshold();
    }

    /**
     * Get structuring min transactions (structuring.min_transactions).
     */
    protected static function getStructuringMinTransactions(): int
    {
        return self::thresholdService()->getStructuringMinTransactions();
    }

    /**
     * Get structuring hourly window (structuring.hourly_window).
     */
    protected static function getStructuringHourlyWindow(): int
    {
        return self::thresholdService()->getStructuringHourlyWindow();
    }

    /**
     * Get structuring lookup days (structuring.lookup_days).
     */
    protected static function getStructuringLookupDays(): int
    {
        return self::thresholdService()->getStructuringLookupDays();
    }

    /**
     * Get duration warning hours (duration.warning_hours).
     */
    protected static function getDurationWarningHours(): int
    {
        return self::thresholdService()->getDurationWarningHours();
    }

    /**
     * Get duration critical hours (duration.critical_hours).
     */
    protected static function getDurationCriticalHours(): int
    {
        return self::thresholdService()->getDurationCriticalHours();
    }

    /**
     * Get velocity alert threshold (velocity.alert_threshold).
     */
    protected static function getVelocityAlertThreshold(): string
    {
        return self::thresholdService()->getVelocityAlertThreshold();
    }

    /**
     * Get velocity warning threshold (velocity.warning_threshold).
     */
    protected static function getVelocityWarningThreshold(): string
    {
        return self::thresholdService()->getVelocityWarningThreshold();
    }

    /**
     * Get velocity window days (velocity.window_days).
     */
    protected static function getVelocityWindowDays(): int
    {
        return self::thresholdService()->getVelocityWindowDays();
    }

    /**
     * Get velocity amount window hours (velocity.amount_window_hours).
     */
    protected static function getVelocityAmountWindowHours(): int
    {
        return self::thresholdService()->getVelocityAmountWindowHours();
    }

    /**
     * Get geographic high-country weight (geographic_risk.high_country_weight).
     */
    protected static function getGeographicHighCountryWeight(): int
    {
        return self::thresholdService()->getGeographicHighCountryWeight();
    }

    /**
     * Get geographic recent-travel weight (geographic_risk.recent_travel_weight).
     */
    protected static function getGeographicRecentTravelWeight(): int
    {
        return self::thresholdService()->getGeographicRecentTravelWeight();
    }

    /**
     * Get round trip threshold (currency_flow.round_trip_threshold).
     */
    protected static function getRoundTripThreshold(): string
    {
        return self::thresholdService()->getRoundTripThreshold();
    }

    /**
     * Get currency flow lookback days (currency_flow.lookback_days).
     */
    protected static function getCurrencyFlowLookbackDays(): int
    {
        return self::thresholdService()->getCurrencyFlowLookbackDays();
    }

    /**
     * Get AML aggregate threshold (aml.aggregate_threshold).
     */
    protected static function getAmlAggregateThreshold(): string
    {
        return self::thresholdService()->getAmlAggregateThreshold();
    }

    /**
     * Get AML amount threshold (aml.amount_threshold).
     */
    protected static function getAmlAmountThreshold(): string
    {
        return self::thresholdService()->getAmlAmountThreshold();
    }

    /**
     * Get response time warning (performance.response_time_warning).
     */
    protected static function getResponseTimeWarning(): string
    {
        return self::thresholdService()->getResponseTimeWarning();
    }

    /**
     * Get cache hit rate warning (performance.cache_hit_rate_warning).
     */
    protected static function getCacheHitRateWarning(): string
    {
        return self::thresholdService()->getCacheHitRateWarning();
    }

    /**
     * Get query time warning (performance.query_time_warning).
     */
    protected static function getQueryTimeWarning(): string
    {
        return self::thresholdService()->getQueryTimeWarning();
    }

    /**
     * Get job duration warning (performance.job_duration_warning).
     */
    protected static function getJobDurationWarning(): string
    {
        return self::thresholdService()->getJobDurationWarning();
    }

    /**
     * Get KYC grace period days (kyc.grace_period_days).
     */
    protected static function getKycGracePeriodDays(): int
    {
        return self::thresholdService()->getKycGracePeriodDays();
    }

    /**
     * Get risk review batch size (risk_review.batch_size).
     */
    protected static function getRiskReviewBatchSize(): int
    {
        return self::thresholdService()->getRiskReviewBatchSize();
    }

    /**
     * Get position limit for a currency code (position_limits.{code}).
     */
    protected static function getPositionLimit(string $currencyCode): ?string
    {
        return self::thresholdService()->getPositionLimit($currencyCode);
    }

    /**
     * Get all position limits keyed by upper-case currency code.
     *
     * @return array<string, string>
     */
    protected static function getPositionLimits(): array
    {
        return self::thresholdService()->getPositionLimits();
    }
}
