<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Account Mapping Seed Defaults
    |--------------------------------------------------------------------------
    |
    | Runtime posting paths resolve accounts through the account_mappings
    | table (AccountMappingService) — these env vars are read once by
    | AccountMappingsSeeder so an upgraded install keeps its configured
    | accounts. After seeding, edit mappings under Accounting → Account
    | Mappings; changing these env vars has no runtime effect.
    |
    */

    'forex_position_account' => env('ACCOUNT_FOREX_POSITION'),
    'revaluation_gain_account' => env('ACCOUNT_REVALUATION_GAIN'),
    'revaluation_loss_account' => env('ACCOUNT_REVALUATION_LOSS'),

    'revenue_summary_account' => env('ACCOUNT_REVENUE_SUMMARY'),
    'expense_summary_account' => env('ACCOUNT_EXPENSE_SUMMARY'),
    'retained_earnings_account' => env('ACCOUNT_RETAINED_EARNINGS'),

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
