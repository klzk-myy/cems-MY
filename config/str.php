<?php

return [
    /*
    |--------------------------------------------------------------------------
    | STR (Suspicious Transaction Report) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for BNM goAML STR submission and compliance workflow.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | goAML API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to Bank Negara Malaysia's goAML system.
    |
    */
    'goaml' => [
        // Enable/disable goAML submission
        'enabled' => env('GOAML_ENABLED', false),

        // goAML API endpoint
        'endpoint' => env('GOAML_ENDPOINT', 'https://goaml.bnm.gov.my/api/v1'),

        // API key for authentication
        'api_key' => env('GOAML_API_KEY', ''),

        // Test mode (simulates submissions without actual API calls)
        'test_mode' => env('GOAML_TEST_MODE', false),

        // Test mode response (true = simulate success, false = simulate failure)
        'test_mode_response' => env('GOAML_TEST_MODE_RESPONSE', true),

        /*
        |--------------------------------------------------------------------------
        | Certificate Authentication (PKI)
        |--------------------------------------------------------------------------
        |
        | BNM requires certificate-based authentication for goAML submissions.
        | Certificates should be stored securely (e.g., /etc/ssl/goaml/).
        |
        */
        'cert_path' => env('GOAML_CERT_PATH', ''),
        'cert_password' => env('GOAML_CERT_PASSWORD', ''),
        'key_path' => env('GOAML_KEY_PATH', ''),
        'key_password' => env('GOAML_KEY_PASSWORD', ''),
        'ca_path' => env('GOAML_CA_PATH', ''),

        /*
        |--------------------------------------------------------------------------
        | Retry Configuration
        |--------------------------------------------------------------------------
        |
        | Maximum retry attempts and delays for failed submissions.
        | Delays are in seconds and follow exponential backoff.
        |
        */
        'max_retries' => env('GOAML_MAX_RETRIES', 5),
        'retry_delays' => [60, 300, 600, 1800, 3600], // 1min, 5min, 10min, 30min, 60min

        /*
        |--------------------------------------------------------------------------
        | Timeout Configuration
        |--------------------------------------------------------------------------
        |
        | Connection and request timeouts for goAML API calls.
        |
        */
        'connection_timeout' => 30,
        'request_timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Entity Information
    |--------------------------------------------------------------------------
    |
    | Information about the MSB submitting STRs to BNM.
    |
    */
    'reporter' => [
        'name' => env('GOAML_REPORTER_NAME', config('cems.company_name', 'CEMS-MY MSB')),
        'branch_code' => env('GOAML_BRANCH_CODE', 'HQ'),
        'address' => env('GOAML_REPORTING_ADDRESS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filing Deadlines
    |--------------------------------------------------------------------------
    |
    | BNM requires STR filing within a specific timeframe from when
    | suspicion first arose.
    |
    */
    'filing_deadline_days' => 3,
    'filing_deadline_hours' => 72,

    /*
    |--------------------------------------------------------------------------
    | Escalation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for supervisor escalation on failed submissions.
    |
    */
    'escalation' => [
        'enabled' => true,
        'notify_roles' => ['compliance_officer', 'manager', 'admin'],
        'auto_escalate_after_retries' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | XML Generation
    |--------------------------------------------------------------------------
    |
    | Configuration for goAML XML generation.
    |
    */
    'xml' => [
        'version' => '1.0',
        'encoding' => 'UTF-8',
        'namespace' => 'urn:goAML:report:1.0',
        'format_output' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Masking
    |--------------------------------------------------------------------------
    |
    | Configuration for masking sensitive customer data in goAML submissions.
    |
    */
    'masking' => [
        'id_number' => true,
        'address' => true,
        'phone' => true,
        'email' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Mode Mock Server
    |--------------------------------------------------------------------------
    |
    | Configuration for local mock goAML server for testing.
    |
    */
    'mock_server' => [
        'enabled' => env('GOAML_MOCK_ENABLED', false),
        'port' => env('GOAML_MOCK_PORT', 8080),
        'log_requests' => true,
        'simulate_failures' => false,
    ],
];
