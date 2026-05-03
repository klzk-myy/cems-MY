<?php

return [
    'layout' => 'livewire.layouts.default',

    'component_namespaces' => [
        'layouts' => resource_path('views/livewire/layouts'),
        'pages' => resource_path('views/livewire/pages'),
    ],

    'comments' => [
        'enabled' => env('APP_ENV', 'production') === 'local',
    ],
    'inject_assets' => true,
    'pagination' => [
        'theme' => 'tailwind',
        'icons' => 'tailwind',
    ],
];
