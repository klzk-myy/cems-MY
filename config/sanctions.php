<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sanction / watchlist sources
    |--------------------------------------------------------------------------
    | Each key becomes a sanction_lists.slug. list_type maps through
    | SanctionListSeeder to SanctionListType:
    |   national        -> MOHA   (domestic TFS list, AMLATFPUAA s.66B)
    |   domestic_alert  -> Domestic (BNM/SC regulatory alert lists)
    |   international   -> UNSCR  (UN and foreign sanctions regimes)
    |   internal        -> Internal (institution-maintained watchlists)
    |
    | default_list controls whether the seeder activates the list and whether
    | the webhook may dispatch an update for it.
    */
    'sources' => [
        // --- Mandatory Malaysian domestic lists -------------------------------
        'moha_malaysia' => [
            'name' => 'MOHA Malaysia Sanctions List',
            'url' => env('SANCTIONS_MOHA_URL', 'https://data.opensanctions.org/datasets/latest/my_moha_sanctions/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'weekly',
            'list_type' => 'national',
            'default_list' => true,
        ],
        'my_consumer_alert' => [
            'name' => 'Malaysia Financial Consumer Alert List (BNM)',
            'url' => env('SANCTIONS_CONSUMER_ALERT_URL', 'https://data.opensanctions.org/datasets/latest/my_consumer_alert_list/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'domestic_alert',
            'default_list' => true,
        ],
        'my_investor_alert' => [
            'name' => 'Malaysia SC Investor Alert List',
            'url' => env('SANCTIONS_INVESTOR_ALERT_URL', 'https://data.opensanctions.org/datasets/latest/my_investor_alert_list/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'domestic_alert',
            'default_list' => true,
        ],
        'my_sc_aob' => [
            'name' => 'Malaysia SC AOB Enforcements',
            'url' => env('SANCTIONS_SC_AOB_URL', 'https://data.opensanctions.org/datasets/latest/my_aob_sanctions/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'domestic_alert',
            'default_list' => true,
        ],

        // --- Mandatory international (UNSCR, BNM "without delay") -------------
        'un_consolidated' => [
            'name' => 'UN Security Council Consolidated',
            'url' => env('SANCTIONS_UN_URL', 'https://data.opensanctions.org/datasets/latest/un_sc_sanctions/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'international',
            'default_list' => true,
        ],

        // --- Risk-based / extra-territorial (USD, GBP, EUR corridors) ---------
        'ofac_sdn' => [
            'name' => 'US OFAC SDN List',
            'url' => env('SANCTIONS_OFAC_URL', 'https://data.opensanctions.org/datasets/latest/us_ofac_sdn/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'international',
            'default_list' => true,
        ],
        'gb_hmt' => [
            'name' => 'UK Sanctions List (UKSL/FCDO)',
            'url' => env('SANCTIONS_HMT_URL', 'https://data.opensanctions.org/datasets/latest/gb_fcdo_sanctions/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'international',
            'default_list' => true,
        ],
        'eu_consolidated' => [
            'name' => 'EU Consolidated Sanctions',
            'url' => env('SANCTIONS_EU_URL', 'https://data.opensanctions.org/datasets/latest/eu_fsf/targets.nested.json'),
            'format' => 'JSON',
            'frequency' => 'daily',
            'list_type' => 'international',
            'default_list' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Download allowlist
    |--------------------------------------------------------------------------
    | Only these hosts (and their subdomains) may be fetched by the sanctions
    | download/import pipeline. Covers the configured OpenSanctions mirrors and
    | the official publisher endpoints.
    */
    'allowed_hosts' => [
        'opensanctions.org',
        'data.opensanctions.org',
        'sanctions.gov.my',
        'ofac.treasury.gov',
        'sdnlists.ofac.treasury.gov',
        'europa.eu',
        'un.org',
        'unescritor.org',
    ],

    /*
    |--------------------------------------------------------------------------
    | Download pipeline (SanctionsDownloadService)
    |--------------------------------------------------------------------------
    */
    'download' => [
        'temp_directory' => env('SANCTIONS_TEMP_DIR', storage_path('app/temp/sanctions')),
        'archive_directory' => env('SANCTIONS_ARCHIVE_DIR', storage_path('app/archive/sanctions')),
        'timeout' => (int) env('SANCTIONS_DOWNLOAD_TIMEOUT', 300),
        'user_agent' => env('SANCTIONS_USER_AGENT', 'CEMS-MY/1.0'),
        'retry_attempts' => (int) env('SANCTIONS_DOWNLOAD_RETRIES', 3),
        'retry_delay' => (int) env('SANCTIONS_DOWNLOAD_RETRY_DELAY', 60),
        'archive_retention_days' => (int) env('SANCTIONS_ARCHIVE_RETENTION_DAYS', 30),
        // Orphaned temp files (crashed/interrupted imports) are pruned after
        // this many hours; successfully imported temp files are deleted inline.
        'temp_retention_hours' => (int) env('SANCTIONS_TEMP_RETENTION_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | External update webhook (SanctionsWebhookController)
    |--------------------------------------------------------------------------
    | The webhook stays disabled (always 401) until a token is configured.
    */
    'webhook' => [
        'token' => env('SANCTIONS_WEBHOOK_TOKEN', ''),
    ],

    'matching' => [
        'threshold_flag' => 75.0,
        'threshold_block' => 90.0,
        'algorithm' => 'levenshtein',
        'use_dob' => true,
        'use_nationality' => true,
        'max_candidates' => 100,
    ],

    'import' => [
        'timeout' => 300,
        'retry_attempts' => 3,
        'retry_delay' => 60,
        'fallback_continue' => true,
    ],
];
