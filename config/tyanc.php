<?php

declare(strict_types=1);

return [
    'admin_path' => env('TYANC_ADMIN_PATH', 'tyanc'),
    'demo_path' => env('TYANC_DEMO_PATH', 'demo'),
    'default_app' => env('TYANC_DEFAULT_APP', 'tyanc'),
    'api_domain' => env('TYANC_API_DOMAIN', 'api.tyanc.test'),
    'api_prefix' => env('TYANC_API_PREFIX', 'api/v1'),
    'reserved_roles' => [
        'super_admin' => 'Supa Manuse',
        'admin' => 'Manuse',
    ],
    'theme' => [
        'appearance' => 'system',
        'sidebar_variant' => 'inset',
        'spacing_density' => 1,
        'radius' => '0.625rem',
    ],
];
