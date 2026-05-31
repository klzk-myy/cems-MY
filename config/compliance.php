<?php

return [
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
