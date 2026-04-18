<?php

/**
 * Security configuration for CEMS-MY.
 *
 * This file contains security-related settings including:
 * - HSTS (HTTP Strict Transport Security)
 * - Rate limiting
 * - Session security
 * - Password policies
 *
 * BNM Compliance: These settings help meet AML/CFT security requirements.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | HSTS instructs browsers to always use HTTPS connections.
    | Required for BNM compliance on production systems.
    |
    */

    'hsts_max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000), // 1 year
    'hsts_include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
    'hsts_preload' => env('SECURITY_HSTS_PRELOAD', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | BNM requires rate limiting to prevent brute force attacks
    | and ensure system availability.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration (BNM Compliant)
    |--------------------------------------------------------------------------
    |
    | Hardened rate limiting to prevent brute force attacks, abuse,
    | and ensure system availability per BNM security requirements.
    |
    | Note: Burst protection allows small bursts but enforces average rates
    |
    */

    'rate_limits' => [
        // Login: 5 attempts per minute per IP
        'login' => [
            'attempts' => 5,
            'per_minutes' => 1,
            'burst_allowance' => 3, // Allow burst of 3 requests
            'decay_minutes' => 1,
        ],
        // API general: 30 per minute per IP (reduced from 60)
        'api' => [
            'attempts' => 30,
            'per_minutes' => 1,
            'burst_allowance' => 10,
            'decay_minutes' => 1,
        ],
        // Transactions: 10 per minute per user (reduced from 30)
        'transactions' => [
            'attempts' => 10,
            'per_minutes' => 1,
            'burst_allowance' => 3,
            'decay_minutes' => 1,
        ],
        // STR submission: 3 per minute per user (reduced from 10)
        'str' => [
            'attempts' => 3,
            'per_minutes' => 1,
            'burst_allowance' => 1,
            'decay_minutes' => 1,
        ],
        // Bulk operations: 1 per 5 minutes per user
        'bulk' => [
            'attempts' => 1,
            'per_minutes' => 5,
            'burst_allowance' => 1,
            'decay_minutes' => 5,
        ],
        // Export operations: 5 per minute per user
        'export' => [
            'attempts' => 5,
            'per_minutes' => 1,
            'burst_allowance' => 2,
            'decay_minutes' => 1,
        ],
        // Sensitive operations (MFA, password change): 3 per minute
        'sensitive' => [
            'attempts' => 3,
            'per_minutes' => 1,
            'burst_allowance' => 1,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IP-Based Blocking Configuration
    |--------------------------------------------------------------------------
    |
    | Auto-block IPs after repeated failed attempts to prevent brute force.
    | Blocked IPs are stored in Redis for fast lookup.
    |
    */

    'ip_blocking' => [
        'enabled' => filter_var(env('SECURITY_IP_BLOCKING_ENABLED', true), FILTER_VALIDATE_BOOL),
        // Block IP after 10 failed login attempts in 5 minutes
        'failed_attempts_threshold' => 10,
        'time_window_minutes' => 5,
        // Block duration in minutes (default 1 hour)
        'block_duration_minutes' => env('SECURITY_IP_BLOCK_DURATION', 60),
        // Maximum block duration for repeat offenders (24 hours)
        'max_block_duration_minutes' => 1440,
        // Whitelisted IPs (never block) - supports exact IPs and CIDR notation
        // Examples: 192.168.1.1, 10.0.0.0/8, 172.16.0.0/12, 127.0.0.1
        'whitelist' => array_filter(explode(',', env('SECURITY_IP_WHITELIST', '192.168.1.0/24,127.0.0.1'))),
        // Cache key prefix for Redis
        'cache_prefix' => 'ip_block:',
        'failed_attempts_prefix' => 'ip_failed:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Monitoring & Alerting
    |--------------------------------------------------------------------------
    |
    | Alert when same IP hits limits repeatedly, indicating potential attack.
    |
    */

    'rate_limit_monitoring' => [
        'enabled' => env('SECURITY_RATE_LIMIT_MONITORING', true),
        // Alert threshold: same IP hits limit 3 times in 10 minutes
        'alert_threshold' => 3,
        'alert_window_minutes' => 10,
        // Log rate limit hits
        'log_hits' => true,
        // Store hit history for analysis (in minutes)
        'hit_history_ttl' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Secure session configuration for BNM compliance.
    |
    */

    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 480), // 8 hours (480 minutes)
        'expire_on_close' => false,
        'secure' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => true,
        'same_site' => 'strict',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | BNM requires minimum 12 characters with mixed case, numbers, and symbols.
    |
    */

    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'max_attempts' => 5,
        'lockout_duration' => 15, // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log Security
    |--------------------------------------------------------------------------
    |
    | Tamper-evident audit logging configuration.
    |
    */

    'audit' => [
        'hash_algorithm' => 'sha256',
        'retention_days' => env('AUDIT_RETENTION_DAYS', 2555), // 7 years (BNM requirement)
        'encrypt_sensitive' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | CSP directives for XSS protection.
    |
    */

    'csp' => [
        'enabled' => env('SECURITY_CSP_ENABLED', true),
        'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),
        'report_uri' => env('SECURITY_CSP_REPORT_URI', null),
    ],
];
