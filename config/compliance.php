<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CTR Warning Threshold
    |--------------------------------------------------------------------------
    |
    | Warning threshold for CTR reporting (approaching the main threshold).
    | The actual CTR threshold is defined in config/thresholds.php.
    |
    */

    'ctr_warning_threshold' => env('CTR_WARNING_THRESHOLD', 20000),

    /*
    |--------------------------------------------------------------------------
    | CTOS (Cash Transaction Reporting to BNM) Settings
    |--------------------------------------------------------------------------
    */

    'ctos_enabled' => env('CTOS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | STR (Suspicious Transaction Report) Settings
    |--------------------------------------------------------------------------
    */

    'str_auto_generate' => env('STR_AUTO_GENERATE', true),
    'str_approval_required' => env('STR_APPROVAL_REQUIRED', true),
];
