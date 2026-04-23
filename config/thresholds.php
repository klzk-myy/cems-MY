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
        'auto_approve' => env('THRESHOLD_AUTO_APPROVE', '3000'),
        'manager' => env('THRESHOLD_MANAGER', '50000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CDD (Customer Due Diligence) Thresholds
    |--------------------------------------------------------------------------
    |
    | Simplified: < standard
    | Standard: >= standard AND < large_transaction
    | Enhanced: >= large_transaction OR PEP/sanctions/high risk
    |
    */
    'cdd' => [
        'standard' => env('THRESHOLD_CDD_STANDARD', '3000'),
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
        'ctos' => env('THRESHOLD_CTOS', '10000'),
        'ctr' => env('THRESHOLD_CTR', '50000'),
        'str' => env('THRESHOLD_STR', '50000'),
        'edd' => env('THRESHOLD_EDD', '50000'),
        'lctr' => env('THRESHOLD_LCTR', '50000'),
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
];
