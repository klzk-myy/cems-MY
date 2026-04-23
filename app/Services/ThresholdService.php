<?php

namespace App\Services;

use App\Models\ThresholdAudit;
use Illuminate\Support\Facades\Log;

class ThresholdService
{
    /**
     * Fallback constants for backward compatibility.
     * These match the values in the original service constants.
     */
    public const FALLBACK_AUTO_APPROVE = '3000';

    public const FALLBACK_MANAGER = '50000';

    public const FALLBACK_CDD_STANDARD = '3000';

    public const FALLBACK_CDD_LARGE = '50000';

    public const FALLBACK_CTOS = '10000';

    public const FALLBACK_CTR = '25000';

    public const FALLBACK_LCTR = '25000';

    public const FALLBACK_STR = '50000';

    public const FALLBACK_EDD = '50000';

    public const FALLBACK_RISK_HIGH = '50000';

    public const FALLBACK_RISK_MEDIUM = '30000';

    public const FALLBACK_RISK_LOW = '10000';

    public const FALLBACK_ALERT_CRITICAL = '50000';

    public const FALLBACK_ALERT_HIGH = '30000';

    public const FALLBACK_ALERT_MEDIUM = '10000';

    public const FALLBACK_VARIANCE_YELLOW = '100.00';

    public const FALLBACK_VARIANCE_RED = '500.00';

    public const FALLBACK_STRUCTURING_SUB = '3000';

    public const FALLBACK_STRUCTURING_MIN_TXNS = 3;

    public const FALLBACK_DURATION_WARNING = 24;

    public const FALLBACK_DURATION_CRITICAL = 48;

    public const FALLBACK_VELOCITY_ALERT = '50000';

    public const FALLBACK_VELOCITY_WARNING = '45000';

    public const FALLBACK_ROUND_TRIP = '5000';

    public const FALLBACK_CURRENCY_FLOW_LOOKBACK_DAYS = 7;

    /**
     * Get a threshold value from config, with fallback to constant.
     */
    public function get(string $category, string $key, ?string $fallbackConstant = null): string|int|float
    {
        $value = config("thresholds.{$category}.{$key}");

        if ($value !== null) {
            return $value;
        }

        if ($fallbackConstant !== null) {
            return $this->getFallbackValue($fallbackConstant);
        }

        throw new \RuntimeException("Threshold not found: {$category}.{$key}");
    }

    /**
     * Get fallback value from a constant name.
     */
    protected function getFallbackValue(string $constantName): string|int
    {
        if (defined("self::{$constantName}")) {
            return constant("self::{$constantName}");
        }

        $parts = explode('::', $constantName);
        if (count($parts) === 2) {
            [$class, $property] = $parts;
            $fullClass = "App\\Services\\{$class}";
            if (class_exists($fullClass) && defined("{$fullClass}::{$property}")) {
                return constant("{$fullClass}::{$property}");
            }
        }

        throw new \RuntimeException("Fallback constant not found: {$constantName}");
    }

    /**
     * Log threshold change for audit purposes.
     */
    protected function auditChange(string $category, string $key, string $oldValue, string $newValue, ?string $reason = null): void
    {
        try {
            ThresholdAudit::create([
                'category' => $category,
                'key' => $key,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed_by' => auth()->id(),
                'change_reason' => $reason,
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create threshold audit log', [
                'category' => $category,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Approval thresholds

    public function getAutoApproveThreshold(): string
    {
        return (string) $this->get('approval', 'auto_approve', 'FALLBACK_AUTO_APPROVE');
    }

    public function getManagerApprovalThreshold(): string
    {
        return (string) $this->get('approval', 'manager', 'FALLBACK_MANAGER');
    }

    // CDD thresholds

    public function getStandardCddThreshold(): string
    {
        return (string) $this->get('cdd', 'standard', 'FALLBACK_CDD_STANDARD');
    }

    public function getLargeTransactionThreshold(): string
    {
        return (string) $this->get('cdd', 'large_transaction', 'FALLBACK_CDD_LARGE');
    }

    // Reporting thresholds

    public function getCtosThreshold(): string
    {
        return (string) $this->get('reporting', 'ctos', 'FALLBACK_CTOS');
    }

    public function getCtrThreshold(): string
    {
        return (string) $this->get('reporting', 'ctr', 'FALLBACK_CTR');
    }

    public function getStrThreshold(): string
    {
        return (string) $this->get('reporting', 'str', 'FALLBACK_STR');
    }

    public function getEddThreshold(): string
    {
        return (string) $this->get('reporting', 'edd', 'FALLBACK_EDD');
    }

    public function getLctrThreshold(): string
    {
        return (string) $this->get('reporting', 'lctr', 'FALLBACK_LCTR');
    }

    // Risk scoring thresholds

    public function getRiskHighThreshold(): string
    {
        return (string) $this->get('risk_scoring', 'high', 'FALLBACK_RISK_HIGH');
    }

    public function getRiskMediumThreshold(): string
    {
        return (string) $this->get('risk_scoring', 'medium', 'FALLBACK_RISK_MEDIUM');
    }

    public function getRiskLowThreshold(): string
    {
        return (string) $this->get('risk_scoring', 'low', 'FALLBACK_RISK_LOW');
    }

    // Alert triage thresholds

    public function getAlertCriticalThreshold(): string
    {
        return (string) $this->get('alert_triage', 'critical', 'FALLBACK_ALERT_CRITICAL');
    }

    public function getAlertHighThreshold(): string
    {
        return (string) $this->get('alert_triage', 'high', 'FALLBACK_ALERT_HIGH');
    }

    public function getAlertMediumThreshold(): string
    {
        return (string) $this->get('alert_triage', 'medium', 'FALLBACK_ALERT_MEDIUM');
    }

    // Variance thresholds

    public function getVarianceYellowThreshold(): string
    {
        return (string) $this->get('variance', 'yellow', 'FALLBACK_VARIANCE_YELLOW');
    }

    public function getVarianceRedThreshold(): string
    {
        return (string) $this->get('variance', 'red', 'FALLBACK_VARIANCE_RED');
    }

    // Structuring thresholds

    public function getStructuringSubThreshold(): string
    {
        return (string) $this->get('structuring', 'sub_threshold', 'FALLBACK_STRUCTURING_SUB');
    }

    public function getStructuringMinTransactions(): int
    {
        return (int) $this->get('structuring', 'min_transactions', 'FALLBACK_STRUCTURING_MIN_TXNS');
    }

    public function getStructuringHourlyWindow(): int
    {
        return (int) $this->get('structuring', 'hourly_window', 1);
    }

    public function getStructuringLookupDays(): int
    {
        return (int) $this->get('structuring', 'lookup_days', 7);
    }

    // Duration thresholds

    public function getDurationWarningHours(): int
    {
        return (int) $this->get('duration', 'warning_hours', 'FALLBACK_DURATION_WARNING');
    }

    public function getDurationCriticalHours(): int
    {
        return (int) $this->get('duration', 'critical_hours', 'FALLBACK_DURATION_CRITICAL');
    }

    // Velocity thresholds

    public function getVelocityAlertThreshold(): string
    {
        return (string) $this->get('velocity', 'alert_threshold', 'FALLBACK_VELOCITY_ALERT');
    }

    public function getVelocityWarningThreshold(): string
    {
        return (string) $this->get('velocity', 'warning_threshold', 'FALLBACK_VELOCITY_WARNING');
    }

    public function getVelocityWindowDays(): int
    {
        return (int) $this->get('velocity', 'window_days', 90);
    }

    // Currency Flow thresholds

    public function getRoundTripThreshold(): string
    {
        return (string) $this->get('currency_flow', 'round_trip_threshold', 'FALLBACK_ROUND_TRIP');
    }

    public function getCurrencyFlowLookbackDays(): int
    {
        return (int) $this->get('currency_flow', 'lookback_days', 'FALLBACK_CURRENCY_FLOW_LOOKBACK_DAYS');
    }
}
