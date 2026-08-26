<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses that receive critical system alerts (data breaches,
    | service outages, security incidents). At least one recipient must be
    | configured for SystemAlertService to send emails.
    |
    | Empty entries are stripped so that an unset SYSTEM_ALERT_RECIPIENTS
    | yields [] rather than [''] (which would pass !empty() checks and mail
    | a blank address).
    |
    */
    'alert_recipients' => array_values(array_filter(
        explode(',', (string) env('SYSTEM_ALERT_RECIPIENTS', '')),
        fn ($recipient) => trim($recipient) !== ''
    )),
];
