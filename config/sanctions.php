<?php

return [
    'sources' => [
        'un_consolidated' => [
            'name' => 'UN Security Council Consolidated',
            'url' => 'https://www.opensanctions.org/datasets/un_sc_sanctions/targets.nested.json',
            'format' => 'json',
            'frequency' => 'daily',
            'list_type' => 'international',
            'default_list' => true,
        ],
        'moha_malaysia' => [
            'name' => 'MOHA Malaysia Sanctions',
            'url' => 'https://www.opensanctions.org/datasets/my_moha_sanctions/targets.nested.json',
            'format' => 'json',
            'frequency' => 'weekly',
            'list_type' => 'national',
            'default_list' => true,
        ],
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
