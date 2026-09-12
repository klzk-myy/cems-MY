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
    'forex_position_account' => env('ACCOUNT_FOREX_POSITION'),
    'revaluation_gain_account' => env('ACCOUNT_REVALUATION_GAIN'),
    'revaluation_loss_account' => env('ACCOUNT_REVALUATION_LOSS'),

    // Period close accounts (used in PeriodCloseService)
    'revenue_summary_account' => env('ACCOUNT_REVENUE_SUMMARY'),
    'expense_summary_account' => env('ACCOUNT_EXPENSE_SUMMARY'),
    'retained_earnings_account' => env('ACCOUNT_RETAINED_EARNINGS'),

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

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year End
    |--------------------------------------------------------------------------
    |
    | Default fiscal year closing date (month/day). Fiscal years close on
    | 31 December by default; override via .env for non-calendar year-ends.
    |
    */
    'fiscal_year_end_month' => env('FISCAL_YEAR_END_MONTH', 12),
    'fiscal_year_end_day' => env('FISCAL_YEAR_END_DAY', 31),
];
