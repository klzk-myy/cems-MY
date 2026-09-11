<?php

return [
    /*
    |--------------------------------------------------------------------------
    | STR (Suspicious Transaction Report) Settings
    |--------------------------------------------------------------------------
    */

    'str_auto_generate' => env('STR_AUTO_GENERATE', true),
    'str_approval_required' => env('STR_APPROVAL_REQUIRED', true),

    /*
    | Minutes a persisted ScreeningResult is considered fresh enough to reuse
    | instead of re-running the fuzzy sanctions screen (avoids double
    | screening per transaction: pre-validation + monitoring hold check).
    */
    'screening_reuse_minutes' => (int) env('COMPLIANCE_SCREENING_REUSE_MINUTES', 60),

    // When true, monitoring always re-screens instead of reusing a recent
    // ScreeningResult (double-check mode). Default off.
    'rescreen_on_monitor' => (bool) env('COMPLIANCE_RESCREEN_ON_MONITOR', false),

    'public_holidays' => (function () {
        $holidays = (string) env('BNM_PUBLIC_HOLIDAYS', '');

        return $holidays ? explode(',', $holidays) : [];
    })(),

    'domestic_nationalities' => array_map('trim', explode(',', (string) env('DOMESTIC_NATIONALITIES', 'Malaysian,Malaysia'))),

    /*
    | Required customer document types per CDD level. Arrays belong here rather
    | than config/thresholds.php, which is convention-restricted to scalars.
    */
    'cdd_required_documents' => [
        'Simplified' => ['MyKad'],
        'Specific' => ['MyKad', 'Proof_of_Address'],
        'Standard' => ['MyKad', 'Proof_of_Address'],
        'Enhanced' => ['MyKad', 'Proof_of_Address', 'Passport'],
    ],
];
