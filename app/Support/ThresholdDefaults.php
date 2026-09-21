<?php

namespace App\Support;

/**
 * Built-in defaults for every env-backed threshold in config/thresholds.php.
 *
 * Single source of truth referenced by both config/thresholds.php (as the
 * env() fallback) and ThresholdService getters (as the last-resort fallback
 * when config is missing). Kept in a value-only class so configuration files
 * do not depend on a service class.
 */
final class ThresholdDefaults
{
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

    /**
     * Aggregate MYR that sub-threshold bookings must reach before a
     * structuring finding is raised — 80% of the auto-approve line (RM10k).
     */
    public const FALLBACK_STRUCTURING_AGGREGATE = '8000';

    public const FALLBACK_STRUCTURING_MIN_TXNS = 3;

    public const FALLBACK_STRUCTURING_HOURLY_WINDOW = 1;

    public const FALLBACK_STRUCTURING_LOOKUP_DAYS = 7;

    public const FALLBACK_DURATION_WARNING = 24;

    public const FALLBACK_DURATION_CRITICAL = 48;

    public const FALLBACK_VELOCITY_ALERT = '50000';

    public const FALLBACK_VELOCITY_WARNING = '45000';

    public const FALLBACK_VELOCITY_WINDOW_DAYS = 90;

    public const FALLBACK_VELOCITY_AMOUNT_WINDOW_HOURS = 24;

    public const FALLBACK_GEO_HIGH_COUNTRY_WEIGHT = 30;

    public const FALLBACK_GEO_RECENT_TRAVEL_WEIGHT = 15;

    public const FALLBACK_RESPONSE_TIME_WARNING = '500';

    public const FALLBACK_CACHE_HIT_RATE_WARNING = '70';

    public const FALLBACK_QUERY_TIME_WARNING = '100';

    public const FALLBACK_JOB_DURATION_WARNING = '5000';

    public const FALLBACK_KYC_GRACE_PERIOD_DAYS = 5;

    public const FALLBACK_RISK_REVIEW_BATCH_SIZE = 50;

    public const FALLBACK_ROUND_TRIP = '5000';

    public const FALLBACK_CURRENCY_FLOW_LOOKBACK_DAYS = 7;

    public const FALLBACK_AML_AGGREGATE = '50000';

    public const FALLBACK_AML_AMOUNT = '50000';

    /**
     * Rate-override limits in percentage points, per BNM compliance:
     * tellers ±0.5%, managers ±2.0%. Roles without a key are unlimited.
     */
    public const FALLBACK_RATE_OVERRIDE_LIMIT_TELLER = '0.5';

    public const FALLBACK_RATE_OVERRIDE_LIMIT_MANAGER = '2.0';

    /**
     * Position-limit utilization bands (percent of the configured limit).
     */
    public const FALLBACK_POSITION_UTILIZATION_WARNING = '75';

    public const FALLBACK_POSITION_UTILIZATION_CRITICAL = '90';
}
