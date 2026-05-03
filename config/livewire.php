<?php

return [
    'layout' => 'livewire.layouts.default',

    'comments' => [
        'enabled' => env('APP_ENV', 'production') === 'local',
    ],
    'inject_assets' => true,
    'pagination' => [
        'theme' => 'tailwind',
        'icons' => 'tailwind',
    ],
];
