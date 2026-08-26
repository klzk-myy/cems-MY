<?php

namespace App\Services;

use App\Exceptions\Domain\ThresholdNotFoundException;
use App\Models\ThresholdAudit;
use App\Services\Contracts\ThresholdServiceInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ThresholdService implements ThresholdServiceInterface
{
    /**
     * In-memory cache of persisted threshold values for the current request.
     * Prevents repeated database queries when the same threshold is read multiple times.
     *
     * @var array<string, string|null>
     */
    private array $persistedValueCache = [];

    /**
     * Fallback constants for backward compatibility.
     * These match the values in the original service constants.
     */
    public const FALLBACK_AUTO_APPROVE = '10000';

    public const FALLBACK_MANAGER = '50000';

    public const FALLBACK_CDD_SPECIFIC = '3000';

    public const FALLBACK_CDD_STANDARD = '10000';

    public const FALLBACK_CDD_LARGE = '50000';

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

    public const FALLBACK_AML_AGGREGATE = '50000';

    public const FALLBACK_AML_AMOUNT = '50000';

    /**
     * Set a threshold value in config, persist to database, and audit the change.
     *
     * The value is both stored in config (for the duration of this request) and
     * persisted in the threshold_audits table (for cross-request durability via
     * the get() method).
     *
     * @param  string  $category  The threshold category (e.g., 'approval', 'cdd')
     * @param  string  $key  The threshold key (e.g., 'auto_approve', 'manager')
     * @param  string|int|float  $value  The new value
     * @param  string|null  $reason  The reason for the change
     * @return bool True if value was changed, false if same
     */
    public function set(string $category, string $key, string|int|float $value, ?string $reason = null): bool
    {
        // Derive the valid categories from the thresholds config itself, plus
        // any code-only categories read by getters (e.g. geographic_risk).
        $allowedCategories = array_merge(
            array_keys(config('thresholds') ?? []),
            ['geographic_risk']
        );
        if (! in_array($category, $allowedCategories, true)) {
            throw new \InvalidArgumentException("Invalid threshold category: {$category}");
        }

        if (preg_match('/^[a-z_]+$/', $key) !== 1) {
            throw new \InvalidArgumentException("Invalid threshold key: {$key}");
        }

        // Get the effective old value (respecting any previously persisted override)
        $oldValue = $this->get($category, $key);

        // If value is same, do not audit or update
        if ((string) $oldValue === (string) $value) {
            return false;
        }

        // Update the config value (immediate, for current request)
        config(["thresholds.{$category}.{$key}" => $value]);

        // Audit the change (persists to DB for cross-request durability)
        $this->auditChange($category, $key, (string) $oldValue, (string) $value, $reason);

        // Clear the in-memory cache so the next get() reads the freshly persisted value.
        unset($this->persistedValueCache["{$category}.{$key}"]);

        return true;
    }

    /**
     * Get a threshold value with persistence chain:
     *   1. Database (persisted overrides from previous set() calls)
     *   2. Config (env variables and config file defaults)
     *   3. Fallback constant
     *
     * IMPORTANT: Config values from config/thresholds.php always have non-null
     * defaults (e.g. env('THRESHOLD_AUTO_APPROVE', '10000')). If we checked config
     * before the database, the DB override would never be read — the env default
     * always wins. Checking the DB first ensures runtime threshold changes made
     * via set() actually take effect across requests.
     */
    public function get(string $category, string $key, string|int|float|null $fallback = null): string|int|float
    {
        $persisted = $this->getPersistedValue($category, $key);
        if ($persisted !== null) {
            config(["thresholds.{$category}.{$key}" => $persisted]);

            return $persisted;
        }

        $value = config("thresholds.{$category}.{$key}");
        if ($value !== null) {
            return $value;
        }

        if ($fallback !== null) {
            if (is_string($fallback)) {
                return $this->getFallbackValue($fallback);
            }

            return $fallback;
        }

        throw new ThresholdNotFoundException("{$category}.{$key}");
    }

    /**
     * Retrieve the most recently persisted value from the threshold_audits table.
     *
     * Returns null if no override has ever been recorded for this key.
     */
    protected function getPersistedValue(string $category, string $key): ?string
    {
        $cacheKey = "{$category}.{$key}";

        if (array_key_exists($cacheKey, $this->persistedValueCache)) {
            return $this->persistedValueCache[$cacheKey];
        }

        try {
            $latest = ThresholdAudit::where('category', $category)
                ->where('key', $key)
                ->latest('changed_at')
                ->first();

            $value = $latest ? (string) $latest->new_value : null;
            $this->persistedValueCache[$cacheKey] = $value;

            return $value;
        } catch (QueryException $e) {
            Log::critical('Failed to read persisted threshold from database', [
                'category' => $category,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Fall back to config defaults rather than failing the entire request.
            // Operators should be alerted via the critical log entry.
            $this->persistedValueCache[$cacheKey] = null;

            return null;
        }
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

        throw new ThresholdNotFoundException($constantName);
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

    public function getSpecificCddThreshold(): string
    {
        return (string) $this->get('cdd', 'specific', 'FALLBACK_CDD_SPECIFIC');
    }

    public function getStandardCddThreshold(): string
    {
        return (string) $this->get('cdd', 'standard', 'FALLBACK_CDD_STANDARD');
    }

    public function getLargeTransactionThreshold(): string
    {
        return (string) $this->get('cdd', 'large_transaction', 'FALLBACK_CDD_LARGE');
    }

    // Reporting thresholds

    public function getStrThreshold(): string
    {
        return (string) $this->get('reporting', 'str', 'FALLBACK_STR');
    }

    public function getEddThreshold(): string
    {
        return (string) $this->get('reporting', 'edd', 'FALLBACK_EDD');
    }

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

    // Lookback window (in hours) for the per-customer amount-threshold velocity
    // check. Contractually a 24-hour window; separate from the 90-day scoring
    // lookback above.
    public function getVelocityAmountWindowHours(): int
    {
        return (int) $this->get('velocity', 'amount_window_hours', 24);
    }

    // Currency Flow thresholds

    // Risk-score point weights for the geographic risk component (not MYR
    // amounts): points added per high-risk nationality / recent-travel hit,
    // and the classification cutoffs derived from them.
    public function getGeographicHighCountryWeight(): int
    {
        return (int) $this->get('geographic_risk', 'high_country_weight', 30);
    }

    public function getGeographicRecentTravelWeight(): int
    {
        return (int) $this->get('geographic_risk', 'recent_travel_weight', 15);
    }

    public function getRoundTripThreshold(): string
    {
        return (string) $this->get('currency_flow', 'round_trip_threshold', 'FALLBACK_ROUND_TRIP');
    }

    public function getCurrencyFlowLookbackDays(): int
    {
        return (int) $this->get('currency_flow', 'lookback_days', 'FALLBACK_CURRENCY_FLOW_LOOKBACK_DAYS');
    }

    // AML thresholds

    public function getAmlAggregateThreshold(): string
    {
        return (string) $this->get('aml', 'aggregate_threshold', 'FALLBACK_AML_AGGREGATE');
    }

    public function getAmlAmountThreshold(): string
    {
        return (string) $this->get('aml', 'amount_threshold', 'FALLBACK_AML_AMOUNT');
    }

    // Performance thresholds

    public function getResponseTimeWarning(): string
    {
        return (string) $this->get('performance', 'response_time_warning', '500');
    }

    public function getCacheHitRateWarning(): string
    {
        return (string) $this->get('performance', 'cache_hit_rate_warning', '70');
    }

    public function getQueryTimeWarning(): string
    {
        return (string) $this->get('performance', 'query_time_warning', '100');
    }

    public function getJobDurationWarning(): string
    {
        return (string) $this->get('performance', 'job_duration_warning', '5000');
    }

    // KYC Document Expiry thresholds

    public function getKycGracePeriodDays(): int
    {
        return (int) $this->get('kyc', 'grace_period_days', 5);
    }

    public function getRiskReviewBatchSize(): int
    {
        return (int) $this->get('risk_review', 'batch_size', 50);
    }
}
