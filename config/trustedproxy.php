<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set the IP addresses or CIDR ranges of any reverse proxies that should
    | be trusted. A value of "*" trusts all proxies, while "**" trusts all
    | proxies and all forwarded headers. Multiple values can be separated by
    | commas.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
