<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Approval Thresholds (higher tier need approval)
    |--------------------------------------------------------------------------
    |
    | Auto-approve: < auto_approve_threshold (no approval needed)
    | Manager approval required: >= manager_threshold
    |
    */
    'approval' => [
        'auto_approve' => env('THRESHOLD_AUTO_APPROVE', '10000'),
        'manager' => env('THRESHOLD_MANAGER', '50000'),
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
        'specific' => env('THRESHOLD_CDD_SPECIFIC', '3000'),
        'standard' => env('THRESHOLD_CDD_STANDARD', '10000'),
        'large_transaction' => env('THRESHOLD_CDD_LARGE', '50000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Scoring Thresholds
    |--------------------------------------------------------------------------
    */
    'risk_scoring' => [
        'high' => env('THRESHOLD_RISK_HIGH', '50000'),
        'medium' => env('THRESHOLD_RISK_MEDIUM', '30000'),
        'low' => env('THRESHOLD_RISK_LOW', '10000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Triage Thresholds
    |--------------------------------------------------------------------------
    */
    'alert_triage' => [
        'critical' => env('THRESHOLD_ALERT_CRITICAL', '50000'),
        'high' => env('THRESHOLD_ALERT_HIGH', '30000'),
        'medium' => env('THRESHOLD_ALERT_MEDIUM', '10000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Thresholds (BNM requirements)
    |--------------------------------------------------------------------------
    */
    'reporting' => [
        'ctos' => env('THRESHOLD_CTOS', '25000'),
        'ctr' => env('THRESHOLD_CTR', '25000'),
        'str' => env('THRESHOLD_STR', '50000'),
        'edd' => env('THRESHOLD_EDD', '50000'),
        'lctr' => env('THRESHOLD_LCTR', '25000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Structuring Detection
    |--------------------------------------------------------------------------
    */
    'structuring' => [
        'sub_threshold' => env('THRESHOLD_STRUCTURING_SUB', '3000'),
        'min_transactions' => env('THRESHOLD_STRUCTURING_MIN_TXNS', 3),
        'hourly_window' => env('THRESHOLD_STRUCTURING_HOURS', 1),
        'lookup_days' => env('THRESHOLD_STRUCTURING_LOOKUP_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Duration Thresholds
    |--------------------------------------------------------------------------
    */
    'duration' => [
        'warning_hours' => env('THRESHOLD_DURATION_WARNING', 24),
        'critical_hours' => env('THRESHOLD_DURATION_CRITICAL', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Counter/Till Variance Thresholds
    |--------------------------------------------------------------------------
    */
    'variance' => [
        'yellow' => env('THRESHOLD_VARIANCE_YELLOW', '100.00'),
        'red' => env('THRESHOLD_VARIANCE_RED', '500.00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Velocity Monitoring
    |--------------------------------------------------------------------------
    */
    'velocity' => [
        'alert_threshold' => env('THRESHOLD_VELOCITY_ALERT', '50000'),
        'warning_threshold' => env('THRESHOLD_VELOCITY_WARNING', '45000'),
        'window_days' => env('THRESHOLD_VELOCITY_WINDOW_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | AML Rules
    |--------------------------------------------------------------------------
    */
    'aml' => [
        'amount_threshold' => env('THRESHOLD_AML_AMOUNT', '50000'),
        'aggregate_threshold' => env('THRESHOLD_AML_AGGREGATE', '50000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Flow Monitoring
    |--------------------------------------------------------------------------
    */
    'currency_flow' => [
        'round_trip_threshold' => env('THRESHOLD_ROUND_TRIP', '5000'),
        'lookback_days' => env('THRESHOLD_CURRENCY_FLOW_LOOKBACK_DAYS', 7),
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
        'max_deviation_percent' => env('RATE_MAX_DEVIATION', '0.05'),
        'precision' => env('RATE_PRECISION', 4),
        'cache_duration' => env('RATE_CACHE_DURATION', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Thresholds
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'response_time_warning' => env('THRESHOLD_RESPONSE_TIME_WARNING', '500'),
        'cache_hit_rate_warning' => env('THRESHOLD_CACHE_HIT_RATE_WARNING', '70'),
        'query_time_warning' => env('THRESHOLD_QUERY_TIME_WARNING', '100'),
        'job_duration_warning' => env('THRESHOLD_JOB_DURATION_WARNING', '5000'),
    ],
];
