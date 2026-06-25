<?php

namespace App\Services\Contracts;

interface ThresholdServiceInterface
{
    public function set(string $category, string $key, string|int|float $value, ?string $reason = null): bool;

    public function get(string $category, string $key, ?string $fallbackConstant = null): string|int|float;

    public function getAutoApproveThreshold(): string;

    public function getManagerApprovalThreshold(): string;

    public function getSpecificCddThreshold(): string;

    public function getStandardCddThreshold(): string;

    public function getLargeTransactionThreshold(): string;

    public function getStrThreshold(): string;

    public function getEddThreshold(): string;

    public function getRiskHighThreshold(): string;

    public function getRiskMediumThreshold(): string;

    public function getRiskLowThreshold(): string;

    public function getAlertCriticalThreshold(): string;

    public function getAlertHighThreshold(): string;

    public function getAlertMediumThreshold(): string;

    public function getVarianceYellowThreshold(): string;

    public function getVarianceRedThreshold(): string;

    public function getStructuringSubThreshold(): string;

    public function getStructuringMinTransactions(): int;

    public function getStructuringHourlyWindow(): int;

    public function getStructuringLookupDays(): int;

    public function getDurationWarningHours(): int;

    public function getDurationCriticalHours(): int;

    public function getVelocityAlertThreshold(): string;

    public function getVelocityWarningThreshold(): string;

    public function getVelocityWindowDays(): int;

    public function getRoundTripThreshold(): string;

    public function getCurrencyFlowLookbackDays(): int;

    public function getAmlAggregateThreshold(): string;

    public function getAmlAmountThreshold(): string;

    public function getResponseTimeWarning(): string;

    public function getCacheHitRateWarning(): string;

    public function getQueryTimeWarning(): string;

    public function getJobDurationWarning(): string;

    public function getKycGracePeriodDays(): int;

    public function getRiskReviewBatchSize(): int;
}
