<?php

return [
    'comments' => [
        'enabled' => env('APP_ENV', 'production') === 'local',
    ],
    'inject_assets' => true,
    'pagination' => [
        'theme' => 'tailwind',
        'icons' => 'tailwind',
    ],
];
