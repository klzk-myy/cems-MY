<?php

namespace App\Services;

use App\Exceptions\Domain\ThresholdNotFoundException;
use App\Models\ThresholdAudit;
use App\Services\Contracts\ThresholdServiceInterface;
use App\Support\ActorContext;
use App\Support\ThresholdDefaults;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ThresholdService implements ThresholdServiceInterface
{
    /**
     * In-memory snapshot of persisted threshold values for the current request.
     * Populated once per instance by loadPersistedValues(); absent keys have
     * no override.
     *
     * @var array<string, string>
     */
    private array $persistedValueCache = [];

    /**
     * Whether the persisted-values snapshot has been loaded.
     */
    private bool $persistedValuesLoaded = false;

    /**
     * Raw config/thresholds.php contents, loaded once per instance.
     * Used to determine file/env defaults without being affected by runtime
     * config mutations from set().
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $configDefaults = null;

    /**
     * Set a threshold value in config, persist to database, and audit the change.
     *
     * The value is both stored in config (for the duration of this request, so
     * direct config('thresholds.*') readers stay consistent within the request)
     * and persisted in the threshold_audits table (for cross-request durability
     * via the get() method).
     *
     * This is a trusted internal API: authorization is enforced by callers
     * (route middleware + controller permission checks), which keeps console
     * commands and jobs usable without an authenticated user.
     *
     * @param  string  $category  The threshold category (e.g., 'approval', 'cdd')
     * @param  string  $key  The threshold key (e.g., 'auto_approve', 'manager')
     * @param  string|int|float  $value  The new value
     * @param  string|null  $reason  The reason for the change
     * @return bool True if value was changed, false if same
     */
    public function set(string $category, string $key, string|int|float $value, ?string $reason = null): bool
    {
        $key = strtolower($key);

        // Valid categories are derived from the thresholds config itself.
        $allowedCategories = array_keys(config('thresholds') ?? []);
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

        // The just-written row is the latest persisted value — reflect it in
        // the in-memory cache so later get() calls in this request see it.
        $this->persistedValueCache["{$category}.{$key}"] = (string) $value;

        return true;
    }

    /**
     * Reset a DB-overridden threshold back to its config default.
     *
     * Appends a reverting audit row via set() — history is never deleted.
     * The default is read from the raw config file so a set() earlier in the
     * same request cannot be mistaken for the file/env default.
     *
     * @return bool True if an override was reverted, false when there was
     *              no override or the override already matched the default
     */
    public function reset(string $category, string $key, ?string $reason = null): bool
    {
        $key = strtolower($key);

        $defaults = $this->configDefaults();
        if (! isset($defaults[$category]) || ! array_key_exists($key, $defaults[$category])) {
            throw new \InvalidArgumentException("Unknown threshold: {$category}.{$key}");
        }

        $default = $defaults[$category][$key];

        if (! $this->isOverridden($category, $key)) {
            return false;
        }

        return $this->set(
            $category,
            $key,
            (string) $default,
            $reason ?? 'Reset to config default'
        );
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
     *
     * This method MUST NOT write to the config repository: get() runs inside
     * long-lived Horizon workers, and a read-side write would leak DB overrides
     * into later jobs' config('thresholds.*') reads and diverge from consumers
     * that read the config directly.
     */
    public function get(string $category, string $key, string|int|float|null $fallback = null): string|int|float
    {
        $key = strtolower($key);

        $persisted = $this->getPersistedValue($category, $key);
        if ($persisted !== null) {
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
     * Returns null if no override has ever been recorded for this key. The
     * first call bulk-loads the latest row per key in a single query, so N
     * threshold reads on one instance cost one query, not N.
     */
    protected function getPersistedValue(string $category, string $key): ?string
    {
        $this->loadPersistedValues();

        return $this->persistedValueCache["{$category}.{$key}"] ?? null;
    }

    /**
     * Snapshot the latest persisted value per category.key into
     * $persistedValueCache. Absent keys simply have no override — they are
     * not stored. Rows written via set() in this request are newer than any
     * snapshot row, so existing cache entries are never overwritten.
     */
    private function loadPersistedValues(): void
    {
        if ($this->persistedValuesLoaded) {
            return;
        }
        $this->persistedValuesLoaded = true;

        try {
            $latestIds = ThresholdAudit::query()
                ->selectRaw('MAX(id) as id')
                ->groupBy('category', 'key');

            ThresholdAudit::query()
                ->select('category', 'key', 'new_value')
                ->whereIn('id', $latestIds)
                ->get()
                ->each(function (ThresholdAudit $audit) {
                    $this->persistedValueCache["{$audit->category}.{$audit->key}"] ??= (string) $audit->new_value;
                });
        } catch (QueryException $e) {
            Log::critical('Failed to read persisted thresholds from database', [
                'error' => $e->getMessage(),
            ]);
            // Fall back to config defaults rather than failing the entire
            // request. Operators should be alerted via the critical log entry.
        }
    }

    /**
     * File/env defaults straight from config/thresholds.php, bypassing the
     * runtime config repository so values written by set() cannot leak in.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configDefaults(): array
    {
        return $this->configDefaults ??= require base_path('config/thresholds.php');
    }

    /**
     * Latest audit row per category.key, keyed "category.key". Because the
     * audits table doubles as the override store, the latest row for a key
     * is its live override. Fetches only the newest row per key rather than
     * scanning the whole append-only table.
     *
     * @return array<string, ThresholdAudit>
     */
    public function latestOverrides(): array
    {
        $latestIds = ThresholdAudit::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('category', 'key');

        $overrides = [];

        ThresholdAudit::with('user')
            ->whereIn('id', $latestIds)
            ->get()
            ->each(function (ThresholdAudit $audit) use (&$overrides) {
                $overrides["{$audit->category}.{$audit->key}"] = $audit;
            });

        return $overrides;
    }

    /**
     * Overrides that actually change the effective value — i.e. the latest
     * audit row differs from the file/env config default (or the key has no
     * config default at all). A reverting reset row is history, not an
     * active override, and is excluded.
     *
     * @return array<string, ThresholdAudit>
     */
    public function activeOverrides(): array
    {
        $defaults = $this->configDefaults();
        $active = [];

        foreach ($this->latestOverrides() as $compound => $audit) {
            [$category, $key] = explode('.', $compound, 2);
            $configValue = $defaults[$category][$key] ?? null;

            if ($configValue === null || (string) $audit->new_value !== (string) $configValue) {
                $active[$compound] = $audit;
            }
        }

        return $active;
    }

    /**
     * Whether one key's effective value currently comes from a DB override
     * rather than config.
     */
    public function isOverridden(string $category, string $key): bool
    {
        $key = strtolower($key);

        $latest = ThresholdAudit::where('category', $category)
            ->where('key', $key)
            ->latest('changed_at')
            ->latest('id')
            ->first();

        if ($latest === null) {
            return false;
        }

        $configValue = $this->configDefaults()[$category][$key] ?? null;

        return $configValue === null || (string) $latest->new_value !== (string) $configValue;
    }

    /**
     * Get fallback value from a constant name.
     *
     * Bare names (e.g. 'FALLBACK_AUTO_APPROVE') resolve on
     * App\Support\ThresholdDefaults; 'Class::CONST' names resolve against
     * App\Support and App\Services for backward compatibility.
     */
    protected function getFallbackValue(string $constantName): string|int
    {
        if (defined(ThresholdDefaults::class."::{$constantName}")) {
            return constant(ThresholdDefaults::class."::{$constantName}");
        }

        $parts = explode('::', $constantName);
        if (count($parts) === 2) {
            [$class, $property] = $parts;
            foreach (["App\\Support\\{$class}", "App\\Services\\{$class}"] as $fullClass) {
                if (class_exists($fullClass) && defined("{$fullClass}::{$property}")) {
                    return constant("{$fullClass}::{$property}");
                }
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
                'changed_by' => ActorContext::capture()->userId,
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

    /**
     * Aggregate MYR across sub-threshold bookings that must be reached
     * before a structuring finding is raised.
     */
    public function getStructuringAggregateTrigger(): string
    {
        return (string) $this->get('structuring', 'aggregate_trigger', 'FALLBACK_STRUCTURING_AGGREGATE');
    }

    public function getStructuringHourlyWindow(): int
    {
        return (int) $this->get('structuring', 'hourly_window', 'FALLBACK_STRUCTURING_HOURLY_WINDOW');
    }

    public function getStructuringLookupDays(): int
    {
        return (int) $this->get('structuring', 'lookup_days', 'FALLBACK_STRUCTURING_LOOKUP_DAYS');
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
        return (int) $this->get('velocity', 'window_days', 'FALLBACK_VELOCITY_WINDOW_DAYS');
    }

    // Lookback window (in hours) for the per-customer amount-threshold velocity
    // check. Contractually a 24-hour window; separate from the 90-day scoring
    // lookback above.
    public function getVelocityAmountWindowHours(): int
    {
        return (int) $this->get('velocity', 'amount_window_hours', 'FALLBACK_VELOCITY_AMOUNT_WINDOW_HOURS');
    }

    // Currency Flow thresholds

    // Risk-score point weights for the geographic risk component (not MYR
    // amounts): points added per high-risk nationality / recent-travel hit,
    // and the classification cutoffs derived from them.
    public function getGeographicHighCountryWeight(): int
    {
        return (int) $this->get('geographic_risk', 'high_country_weight', 'FALLBACK_GEO_HIGH_COUNTRY_WEIGHT');
    }

    public function getGeographicRecentTravelWeight(): int
    {
        return (int) $this->get('geographic_risk', 'recent_travel_weight', 'FALLBACK_GEO_RECENT_TRAVEL_WEIGHT');
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
        return (string) $this->get('performance', 'response_time_warning', 'FALLBACK_RESPONSE_TIME_WARNING');
    }

    public function getCacheHitRateWarning(): string
    {
        return (string) $this->get('performance', 'cache_hit_rate_warning', 'FALLBACK_CACHE_HIT_RATE_WARNING');
    }

    public function getQueryTimeWarning(): string
    {
        return (string) $this->get('performance', 'query_time_warning', 'FALLBACK_QUERY_TIME_WARNING');
    }

    public function getJobDurationWarning(): string
    {
        return (string) $this->get('performance', 'job_duration_warning', 'FALLBACK_JOB_DURATION_WARNING');
    }

    // KYC Document Expiry thresholds

    public function getKycGracePeriodDays(): int
    {
        return (int) $this->get('kyc', 'grace_period_days', 'FALLBACK_KYC_GRACE_PERIOD_DAYS');
    }

    public function getRiskReviewBatchSize(): int
    {
        return (int) $this->get('risk_review', 'batch_size', 'FALLBACK_RISK_REVIEW_BATCH_SIZE');
    }

    // Position limits (foreign-currency units per currency code)

    /**
     * Get the position limit for a currency code, or null when the currency
     * has no configured limit.
     */
    public function getPositionLimit(string $currencyCode): ?string
    {
        try {
            return (string) $this->get('position_limits', strtolower($currencyCode));
        } catch (ThresholdNotFoundException) {
            return null;
        }
    }

    /**
     * Get all configured position limits keyed by upper-case currency code.
     * Includes limits that exist only as persisted DB overrides.
     *
     * @return array<string, string>
     */
    public function getPositionLimits(): array
    {
        $keys = array_keys($this->configDefaults()['position_limits'] ?? []);

        try {
            $keys = array_merge(
                $keys,
                ThresholdAudit::where('category', 'position_limits')->distinct()->pluck('key')->all()
            );
        } catch (QueryException) {
            // Config keys only; overrides can't be read without the table.
        }

        $limits = [];
        foreach (array_unique($keys) as $key) {
            $limits[strtoupper((string) $key)] = (string) $this->get('position_limits', $key);
        }

        return $limits;
    }
}
