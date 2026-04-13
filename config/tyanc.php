<?php

declare(strict_types=1);

$supportedLocales = array_values(array_filter(array_map(
    mb_trim(...),
    explode(',', (string) env('TYANC_SUPPORTED_LOCALES', 'en,id')),
)));

if ($supportedLocales === []) {
    $supportedLocales = ['en', 'id'];
}

$roleNames = [
    'super_admin' => 'Supa Manuse',
    'admin' => 'Manuse',
];

return [
    'admin_path' => env('TYANC_ADMIN_PATH', 'tyanc'),
    'demo_path' => env('TYANC_DEMO_PATH', 'demo'),
    'default_app' => env('TYANC_DEFAULT_APP', 'tyanc'),
    'api_domain' => env('TYANC_API_DOMAIN', 'api.tyanc.test'),
    'api_prefix' => env('TYANC_API_PREFIX', 'api/v1'),
    'reserved_roles' => $roleNames,
    'immutable_roles' => [
        $roleNames['super_admin'],
    ],
    'undeletable_roles' => array_values($roleNames),
    'reserved_users' => [
        'super_admin' => [
            'key' => 'super_admin',
            'name' => 'Supa Manuse',
            'username' => 'supa-manuse',
            'email' => 'supa@app.com',
            'locale' => 'en',
            'timezone' => 'Asia/Makassar',
        ],
        'admin' => [
            'key' => 'admin',
            'name' => 'Manuse',
            'username' => 'manuse',
            'email' => 'manuse@app.com',
            'locale' => 'en',
            'timezone' => 'Asia/Makassar',
        ],
    ],
    'undeletable_user_keys' => ['super_admin', 'admin'],
    'reserved_apps' => [
        'tyanc',
    ],
    'local_bootstrap' => [
        'reserved_password' => env('TYANC_LOCAL_RESERVED_PASSWORD', 'password'),
    ],
    'features' => [
        'imports_enabled' => (bool) env('TYANC_IMPORTS_ENABLED', false),
        'exports_enabled' => (bool) env('TYANC_EXPORTS_ENABLED', false),
    ],
    'theme' => [
        'appearance' => 'system',
        'primary_color' => 'oklch(0.5 0.17 200)',
        'secondary_color' => 'oklch(0.96 0 0)',
        'sidebar_variant' => 'inset',
        'spacing_density' => 'default',
        'radius' => '0.625rem',
        'font_family' => 'geist',
    ],
    'appearance_options' => [
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],
    'supported_locales' => collect($supportedLocales)
        ->mapWithKeys(static fn (string $locale): array => [$locale => match ($locale) {
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
            default => mb_strtoupper($locale),
        }])
        ->all(),
    'sidebar_variants' => [
        'inset' => 'Inset',
        'sidebar' => 'Sidebar',
        'floating' => 'Floating',
    ],
    'spacing_densities' => [
        'compact' => [
            'label' => 'Compact',
            'value' => 0.75,
        ],
        'default' => [
            'label' => 'Default',
            'value' => 1.0,
        ],
        'comfortable' => [
            'label' => 'Comfortable',
            'value' => 1.25,
        ],
    ],
    'font_families' => [
        'geist' => [
            'label' => 'Geist',
            'stack' => "'Geist', ui-sans-serif, system-ui, sans-serif",
        ],
        'instrument-sans' => [
            'label' => 'Instrument Sans',
            'stack' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        ],
        'system' => [
            'label' => 'System UI',
            'stack' => 'ui-sans-serif, system-ui, sans-serif',
        ],
    ],
];
