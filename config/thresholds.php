<?php

use App\Support\ThresholdDefaults;

/*
|--------------------------------------------------------------------------
| Threshold Configuration (canonical)
|--------------------------------------------------------------------------
|
| Single source for compliance/business limits. Resolution order:
| threshold_audits (latest row) → these env-backed values →
| App\Support\ThresholdDefaults::FALLBACK_* constants.
|
| Managed via ThresholdService and /admin/thresholds. A DB override wins
| over env until reset — `php artisan thresholds:check-overrides` lists
| active overrides and the admin page surfaces them.
|
| Threshold-like settings that intentionally live elsewhere:
| - config/sanctions.php matching.threshold_*   — fuzzy-match score bands
| - config/security.php ip_blocking / rate_limit_monitoring — infra guards
| - config/transactions.php import/batch caps   — request-size guards
| - config/compliance.php cdd_required_documents — array structure
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Approval Thresholds (higher tier need approval)
    |--------------------------------------------------------------------------
    |
    | Auto-approve: < auto_approve_threshold AND customer not High risk
    | (no approval needed). Anything larger or riskier needs approval.
    | Manager approval: >= auto_approve; Compliance approval: >= manager_threshold
    |
    */
    'approval' => [
        'auto_approve' => env('THRESHOLD_AUTO_APPROVE', ThresholdDefaults::FALLBACK_AUTO_APPROVE),
        'manager' => env('THRESHOLD_MANAGER', ThresholdDefaults::FALLBACK_MANAGER),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDD (Customer Due Diligence) Thresholds
    |--------------------------------------------------------------------------
    |
    | Per pd-00.md 14C.12 for MSB:
    | Simplified: < specific (typically < RM 3,000)
    | Specific: >= specific AND < standard (RM 3,000 - 10,000)
    | Standard: >= standard (>= RM 10,000)
    | Enhanced: risk-based (PEP, Sanction, High risk) - not amount-based
    |
    */
    'cdd' => [
        'specific' => env('THRESHOLD_CDD_SPECIFIC', ThresholdDefaults::FALLBACK_CDD_SPECIFIC),
        'standard' => env('THRESHOLD_CDD_STANDARD', ThresholdDefaults::FALLBACK_CDD_STANDARD),
        'large_transaction' => env('THRESHOLD_CDD_LARGE', ThresholdDefaults::FALLBACK_CDD_LARGE),
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Scoring Thresholds
    |--------------------------------------------------------------------------
    */
    'risk_scoring' => [
        // Cash/amount thresholds used by AmountRiskService / VelocityRiskService
        // (compare transaction amounts in MYR).
        'high' => env('THRESHOLD_RISK_HIGH', ThresholdDefaults::FALLBACK_RISK_HIGH),
        'medium' => env('THRESHOLD_RISK_MEDIUM', ThresholdDefaults::FALLBACK_RISK_MEDIUM),
        'low' => env('THRESHOLD_RISK_LOW', ThresholdDefaults::FALLBACK_RISK_LOW),
        // risk_score band thresholds (customer score on a 0-100 scale) used by
        // CustomerRepository::getCustomersNeedingRescreening() — never re-use
        // the MYR amount keys above for score comparisons.
        'score_high' => env('THRESHOLD_RISK_SCORE_HIGH', '75'),
        'score_medium' => env('THRESHOLD_RISK_SCORE_MEDIUM', '50'),
        'score_low' => env('THRESHOLD_RISK_SCORE_LOW', '25'),
        // Days since risk_assessed_at before a customer is due for
        // rescreening (CustomerRepository::getCustomersNeedingRescreening).
        'rescreening_days' => env('THRESHOLD_RISK_RESCREENING_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Triage Thresholds
    |--------------------------------------------------------------------------
    */
    'alert_triage' => [
        'critical' => env('THRESHOLD_ALERT_CRITICAL', ThresholdDefaults::FALLBACK_ALERT_CRITICAL),
        'high' => env('THRESHOLD_ALERT_HIGH', ThresholdDefaults::FALLBACK_ALERT_HIGH),
        'medium' => env('THRESHOLD_ALERT_MEDIUM', ThresholdDefaults::FALLBACK_ALERT_MEDIUM),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Thresholds (BNM requirements)
    |--------------------------------------------------------------------------
    */
    'reporting' => [
        'str' => env('THRESHOLD_STR', ThresholdDefaults::FALLBACK_STR),
        'edd' => env('THRESHOLD_EDD', ThresholdDefaults::FALLBACK_EDD),
    ],

    /*
    |--------------------------------------------------------------------------
    | Structuring Detection
    |--------------------------------------------------------------------------
    */
    'structuring' => [
        'sub_threshold' => env('THRESHOLD_STRUCTURING_SUB', ThresholdDefaults::FALLBACK_STRUCTURING_SUB),
        'min_transactions' => env('THRESHOLD_STRUCTURING_MIN_TXNS', ThresholdDefaults::FALLBACK_STRUCTURING_MIN_TXNS),
        'hourly_window' => env('THRESHOLD_STRUCTURING_HOURS', ThresholdDefaults::FALLBACK_STRUCTURING_HOURLY_WINDOW),
        'lookup_days' => env('THRESHOLD_STRUCTURING_LOOKUP_DAYS', ThresholdDefaults::FALLBACK_STRUCTURING_LOOKUP_DAYS),
        // Risk scoring tiers: transactions per hour -> score points.
        'score_min_count_high' => (int) env('THRESHOLD_STRUCTURING_SCORE_HIGH_COUNT', 3),
        'score_high' => (int) env('THRESHOLD_STRUCTURING_SCORE_HIGH', 25),
        'score_min_count_low' => (int) env('THRESHOLD_STRUCTURING_SCORE_LOW_COUNT', 2),
        'score_low' => (int) env('THRESHOLD_STRUCTURING_SCORE_LOW', 10),
        'score_cap' => (int) env('THRESHOLD_STRUCTURING_SCORE_CAP', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Pattern Detection
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // Transactions deviating from the customer's trailing average by more
        // than this multiplier over the lookback window are flagged.
        'unusual_pattern_lookback_days' => (int) env('THRESHOLD_UNUSUAL_LOOKBACK_DAYS', 90),
        'unusual_pattern_multiplier' => (string) env('THRESHOLD_UNUSUAL_MULTIPLIER', '2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance SLAs (hours)
    |--------------------------------------------------------------------------
    */
    'alert_sla_hours' => [
        'critical' => (int) env('SLA_ALERT_CRITICAL', 4),
        'high' => (int) env('SLA_ALERT_HIGH', 8),
        'medium' => (int) env('SLA_ALERT_MEDIUM', 24),
        'low' => (int) env('SLA_ALERT_LOW', 72),
    ],

    'case_sla_hours' => [
        'critical' => (int) env('SLA_CASE_CRITICAL', 24),
        'high' => (int) env('SLA_CASE_HIGH', 48),
        'medium' => (int) env('SLA_CASE_MEDIUM', 120),
        'low' => (int) env('SLA_CASE_LOW', 240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Duration Thresholds
    |--------------------------------------------------------------------------
    */
    'duration' => [
        'warning_hours' => env('THRESHOLD_DURATION_WARNING', ThresholdDefaults::FALLBACK_DURATION_WARNING),
        'critical_hours' => env('THRESHOLD_DURATION_CRITICAL', ThresholdDefaults::FALLBACK_DURATION_CRITICAL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Counter/Till Variance Thresholds
    |--------------------------------------------------------------------------
    */
    'variance' => [
        'yellow' => env('THRESHOLD_VARIANCE_YELLOW', ThresholdDefaults::FALLBACK_VARIANCE_YELLOW),
        'red' => env('THRESHOLD_VARIANCE_RED', ThresholdDefaults::FALLBACK_VARIANCE_RED),
    ],

    /*
    |--------------------------------------------------------------------------
    | Velocity Monitoring
    |--------------------------------------------------------------------------
    */
    'velocity' => [
        'alert_threshold' => env('THRESHOLD_VELOCITY_ALERT', ThresholdDefaults::FALLBACK_VELOCITY_ALERT),
        'warning_threshold' => env('THRESHOLD_VELOCITY_WARNING', ThresholdDefaults::FALLBACK_VELOCITY_WARNING),
        'window_days' => env('THRESHOLD_VELOCITY_WINDOW_DAYS', ThresholdDefaults::FALLBACK_VELOCITY_WINDOW_DAYS),
        'amount_window_hours' => env('THRESHOLD_VELOCITY_AMOUNT_WINDOW_HOURS', ThresholdDefaults::FALLBACK_VELOCITY_AMOUNT_WINDOW_HOURS),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geographic Risk (customer risk score point weights, not MYR amounts)
    |--------------------------------------------------------------------------
    */
    'geographic_risk' => [
        'high_country_weight' => (int) env('THRESHOLD_GEO_HIGH_COUNTRY_WEIGHT', ThresholdDefaults::FALLBACK_GEO_HIGH_COUNTRY_WEIGHT),
        'recent_travel_weight' => (int) env('THRESHOLD_GEO_RECENT_TRAVEL_WEIGHT', ThresholdDefaults::FALLBACK_GEO_RECENT_TRAVEL_WEIGHT),
    ],

    /*
    |--------------------------------------------------------------------------
    | AML Rules
    |--------------------------------------------------------------------------
    */
    'aml' => [
        'amount_threshold' => env('THRESHOLD_AML_AMOUNT', ThresholdDefaults::FALLBACK_AML_AMOUNT),
        'aggregate_threshold' => env('THRESHOLD_AML_AGGREGATE', ThresholdDefaults::FALLBACK_AML_AGGREGATE),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Flow Monitoring
    |--------------------------------------------------------------------------
    */
    'currency_flow' => [
        'round_trip_threshold' => env('THRESHOLD_ROUND_TRIP', ThresholdDefaults::FALLBACK_ROUND_TRIP),
        'lookback_days' => env('THRESHOLD_CURRENCY_FLOW_LOOKBACK_DAYS', ThresholdDefaults::FALLBACK_CURRENCY_FLOW_LOOKBACK_DAYS),
    ],

    /*
    |--------------------------------------------------------------------------
    | Position Limits
    |--------------------------------------------------------------------------
    |
    | Maximum currency holdings per currency code before requiring
    | escalation/reporting to BNM. Values in foreign currency units.
    |
    */
    'position_limits' => [
        'usd' => env('POSITION_LIMIT_USD', '1000000'),
        'eur' => env('POSITION_LIMIT_EUR', '800000'),
        'gbp' => env('POSITION_LIMIT_GBP', '700000'),
        'sgd' => env('POSITION_LIMIT_SGD', '900000'),
        'jpy' => env('POSITION_LIMIT_JPY', '100000000'),
        'aud' => env('POSITION_LIMIT_AUD', '750000'),
        'chf' => env('POSITION_LIMIT_CHF', '700000'),
        'cad' => env('POSITION_LIMIT_CAD', '700000'),
        'hkd' => env('POSITION_LIMIT_HKD', '6000000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Spreads
    |--------------------------------------------------------------------------
    |
    | The spread determines the buy/sell rate difference. A 2% spread means
    | buy rate is 1% below mid and sell rate is 1% above mid.
    |
    */
    'rates' => [
        'spread' => env('RATE_SPREAD', '0.02'),
        'min_spread' => env('RATE_MIN_SPREAD', '0.005'),
        'max_spread' => env('RATE_MAX_SPREAD', '0.05'),
        // Outer sanity band for a teller-submitted rate, expressed as a
        // FRACTION of the market rate — 0.05 = 5%, not 0.05%. Enforced for
        // every booking role; the tighter per-role BNM limits below
        // (override_limit_teller / _manager, in percentage points) are
        // enforced on top of it by RateManagementService.
        'max_deviation_percent' => env('RATE_MAX_DEVIATION', '0.05'),
        'precision' => env('RATE_PRECISION', 8),
        'cache_duration' => env('RATE_CACHE_DURATION', 60),
        // Per-role rate-override limits in percentage points (BNM):
        // tellers ±0.5%, managers ±2.0%. Roles without a key are unlimited.
        'override_limit_teller' => env('RATE_OVERRIDE_LIMIT_TELLER', ThresholdDefaults::FALLBACK_RATE_OVERRIDE_LIMIT_TELLER),
        'override_limit_manager' => env('RATE_OVERRIDE_LIMIT_MANAGER', ThresholdDefaults::FALLBACK_RATE_OVERRIDE_LIMIT_MANAGER),
    ],

    /*
    |--------------------------------------------------------------------------
    | KYC Document Expiry
    |--------------------------------------------------------------------------
    |
    | Grace period before blocking transactions when KYC documents expire.
    | Default: 5 days grace period after document expiry before blocking.
    |
    */
    'kyc' => [
        'grace_period_days' => env('KYC_GRACE_PERIOD_DAYS', ThresholdDefaults::FALLBACK_KYC_GRACE_PERIOD_DAYS),
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Review (Periodic Customer Risk Score Recalculation)
    |--------------------------------------------------------------------------
    |
    | Batch size for processing customers due for risk review.
    |
    */
    'risk_review' => [
        'batch_size' => env('RISK_REVIEW_BATCH_SIZE', ThresholdDefaults::FALLBACK_RISK_REVIEW_BATCH_SIZE),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Thresholds
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'response_time_warning' => env('THRESHOLD_RESPONSE_TIME_WARNING', ThresholdDefaults::FALLBACK_RESPONSE_TIME_WARNING),
        'cache_hit_rate_warning' => env('THRESHOLD_CACHE_HIT_RATE_WARNING', ThresholdDefaults::FALLBACK_CACHE_HIT_RATE_WARNING),
        'query_time_warning' => env('THRESHOLD_QUERY_TIME_WARNING', ThresholdDefaults::FALLBACK_QUERY_TIME_WARNING),
        'job_duration_warning' => env('THRESHOLD_JOB_DURATION_WARNING', ThresholdDefaults::FALLBACK_JOB_DURATION_WARNING),
    ],

    /*
    |--------------------------------------------------------------------------
    | Low Stock Alerts
    |--------------------------------------------------------------------------
    |
    | Total position (foreign units) per active currency below this trips a
    | low-stock SystemAlert (LowStockAlertJob).
    |
    */
    'low_stock' => [
        'threshold' => env('THRESHOLD_LOW_STOCK', '10000'),
    ],
];
