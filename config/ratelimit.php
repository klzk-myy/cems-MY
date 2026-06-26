<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store used for rate limiting and IP blocking. This should be
    | a shared store (e.g. redis) when running multiple workers so that limit
    | state is consistent across processes.
    |
    */

    'store' => env('RATE_LIMIT_CACHE_STORE', 'redis'),

];
