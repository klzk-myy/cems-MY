<?php

return [
    'rate_cache_ttl' => env('POS_RATE_CACHE_TTL', 3600),
    'receipt_storage_path' => env('POS_RECEIPT_STORAGE_PATH', storage_path('app/receipts')),
    'thermal_printer_default' => env('POS_THERMAL_PRINTER_DEFAULT', '58mm'),
];
