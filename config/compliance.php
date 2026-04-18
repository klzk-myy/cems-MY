<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CTR (Cash Transaction Report) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Cash Transaction Reporting per BNM requirements.
    | CTR threshold is RM 25,000 for cash transactions.
    |
    */

    'ctr_threshold' => env('CTR_THRESHOLD', 25000),
    'ctr_warning_threshold' => env('CTR_WARNING_THRESHOLD', 20000),

    /*
    |--------------------------------------------------------------------------
    | CDD (Customer Due Diligence) Thresholds
    |--------------------------------------------------------------------------
    */

    'cdd_simplified_threshold' => env('CDD_SIMPLIFIED_THRESHOLD', 3000),
    'cdd_standard_threshold' => env('CDD_STANDARD_THRESHOLD', 50000),

    /*
    |--------------------------------------------------------------------------
    | CTOS (Cash Transaction Reporting to BNM) Settings
    |--------------------------------------------------------------------------
    */

    'ctos_threshold' => env('CTOS_THRESHOLD', 25000),
    'ctos_enabled' => env('CTOS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | STR (Suspicious Transaction Report) Settings
    |--------------------------------------------------------------------------
    */

    'str_auto_generate' => env('STR_AUTO_GENERATE', true),
    'str_approval_required' => env('STR_APPROVAL_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Risk Scoring Settings
    |--------------------------------------------------------------------------
    */

    'risk_high_threshold' => env('RISK_HIGH_THRESHOLD', 60),
    'risk_medium_threshold' => env('RISK_MEDIUM_THRESHOLD', 30),

    /*
    |--------------------------------------------------------------------------
    | Structuring Detection Settings
    |--------------------------------------------------------------------------
    */

    'structuring_lookup_days' => env('STRUCTURING_LOOKUP_DAYS', 7),
    'structuring_sub_threshold' => env('STRUCTURING_SUB_THRESHOLD', 3000),
    'structuring_min_transactions' => env('STRUCTURING_MIN_TRANSACTIONS', 3),

    /*
    |--------------------------------------------------------------------------
    | Velocity Monitoring Settings
    |--------------------------------------------------------------------------
    */

    'velocity_window_days' => env('VELOCITY_WINDOW_DAYS', 90),
    'velocity_alert_threshold' => env('VELOCITY_ALERT_THRESHOLD', 50000),
];
