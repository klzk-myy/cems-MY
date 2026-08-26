<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for transaction behavior and limits.
    |
    */
    'transaction_cancellation_window_hours' => env('CANCELLATION_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Compliance Lookback Period
    |--------------------------------------------------------------------------
    |
    | Number of days for aggregate transaction lookback in compliance checks.
    | BNM AML/CFT requires up to 7-day lookback for structuring detection.
    |
    */
    'aggregate_lookback_days' => env('AGGREGATE_LOOKBACK_DAYS', 7),

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
        'USD' => 1000000,  // $1M USD max position
        'EUR' => 800000,    // €800K EUR max position
        'GBP' => 700000,    // £700K GBP max position
        'SGD' => 900000,   // S$900K SGD max position
        'JPY' => 100000000, // ¥100M JPY max position
        'AUD' => 750000,   // A$750K AUD max position
        'CHF' => 700000,   // CHF 700K max position
        'CAD' => 700000,   // C$700K CAD max position
        'HKD' => 6000000,  // HK$6M HKD max position
    ],

    /*
    |--------------------------------------------------------------------------
    | BNM Reporting Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'edd' => 50000,      // Enhanced Due Diligence threshold (RM)
        'str' => 50000,      // Suspicious Transaction Report threshold (RM)
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA (Multi-Factor Authentication) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for TOTP-based MFA implementation per BNM security
    | compliance requirements.
    |
    */
    'mfa' => [
        // Enable/disable MFA feature globally
        'enabled' => env('MFA_ENABLED', true),

        // Issuer name shown in authenticator apps
        'issuer' => 'CEMS-MY',

        // TOTP parameters
        'period' => 30,      // Time step in seconds
        'digits' => 6,       // Number of digits in TOTP

        // Roles that are required to set up MFA
        'require_for_roles' => ['admin', 'manager', 'compliance', 'teller'],

        // Grace period (days) after first login to set up MFA
        'grace_days' => 30,

        // Days to remember device when "Remember this device" is checked
        'remember_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Timeout
    |--------------------------------------------------------------------------
    |
    | Session idle timeout in minutes. Users will be logged out after
    | this period of inactivity. Default is 15 minutes per BNM security
    | compliance requirements for MSB systems.
    |
    */
    'session_timeout_minutes' => env('SESSION_TIMEOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | MSB License Configuration
    |--------------------------------------------------------------------------
    |
    | BNM MSB License number for regulatory reporting.
    |
    */
    'license_number' => env('BNM_LICENSE_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Company name for regulatory reports and internal use.
    |
    */
    'company_name' => env('COMPANY_NAME', 'CEMS-MY MSB'),

    // Company registration number printed on customer-facing receipts.
    'company_registration_number' => env('COMPANY_REGISTRATION_NUMBER', ''),

    /*
    |--------------------------------------------------------------------------
    | BNM Reporting Contact
    |--------------------------------------------------------------------------
    |
    | Contact details for BNM regulatory reporting.
    |
    */
    'bnm_reporting' => [
        'contact_name' => env('BNM_CONTACT_NAME', ''),
        'contact_email' => env('BNM_CONTACT_EMAIL', ''),
        'contact_phone' => env('BNM_CONTACT_PHONE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dormancy Threshold
    |--------------------------------------------------------------------------
    |
    | Customers with no transactions for this many months are stamped as
    | dormant by the customers:mark-dormant sweep (monthly schedule).
    |
    */
    'dormancy_months' => (int) env('DORMANCY_MONTHS', 12),

    'system_user_id' => (int) env('SYSTEM_USER_ID', 1),

    'api_rates' => [
        'currencies' => explode(',', (string) env('API_RATES_CURRENCIES', 'USD,EUR,GBP,SGD,AUD,CAD,CHF,JPY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Staleness & Auto Fetch
    |--------------------------------------------------------------------------
    |
    | rate_staleness_hours: how long since the newest exchange_rates row was
    | refreshed before the hourly staleness check raises a system alert.
    |
    | rate_auto_fetch_enabled: opt-in scheduled refresh of rates from the
    | upstream exchange-rate API every two hours (RATE_AUTO_FETCH_ENABLED).
    |
    */
    'rate_staleness_hours' => (int) env('RATE_STALENESS_HOURS', 8),

    'rate_auto_fetch_enabled' => (bool) env('RATE_AUTO_FETCH_ENABLED', false),

    'batch_import' => [
        'columns' => [
            'customer_id',
            'type',
            'currency_code',
            'amount_foreign',
            'rate',
            'purpose',
            'source_of_funds',
            'till_id',
        ],
        'sample_currencies' => explode(',', (string) env('API_RATES_CURRENCIES', 'USD')),
    ],

    'demo' => [
        'opening_balances' => [
            'USD' => '50000.0000',
            'EUR' => '30000.0000',
            'GBP' => '20000.0000',
            'SGD' => '40000.0000',
            'THB' => '100000.0000',
        ],
    ],

];
