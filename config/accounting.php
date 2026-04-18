<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hardcoded Account Codes
    |--------------------------------------------------------------------------
    |
    | These account codes are used for specific accounting operations.
    | They should exist in the chart_of_accounts table and be active.
    | These were previously hardcoded in various services.
    |
    */

    // Revaluation accounts (used in RevaluationService)
    'forex_position_account' => env('ACCOUNT_FOREX_POSITION', '2000'),
    'revaluation_gain_account' => env('ACCOUNT_REVALUATION_GAIN', '5100'),
    'revaluation_loss_account' => env('ACCOUNT_REVALUATION_LOSS', '6100'),

    // Period close accounts (used in PeriodCloseService)
    'revenue_summary_account' => env('ACCOUNT_REVENUE_SUMMARY', '4000'),
    'expense_summary_account' => env('ACCOUNT_EXPENSE_SUMMARY', '5000'),
    'retained_earnings_account' => env('ACCOUNT_RETAINED_EARNINGS', '3100'),

    /*
    |--------------------------------------------------------------------------
    | Account Validation
    |--------------------------------------------------------------------------
    |
    | When true, the system will validate that configured accounts exist
    | in the chart of accounts before using them.
    |
    */
    'validate_accounts' => env('ACCOUNTING_VALIDATE_ACCOUNTS', true),
];
