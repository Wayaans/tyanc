<?php

declare(strict_types=1);

return [
    'apps' => [
        'tyanc' => [
            'title' => 'Tyanc',
            'subtitle' => 'Admin panel',
            'icon' => 'app-logo',
            'route' => 'dashboard',
            'menu' => [
                [
                    'title' => 'Dashboard',
                    'icon' => 'layout-grid',
                    'route' => 'dashboard',
                    'permission' => null,
                ],
                [
                    'title' => 'Settings',
                    'icon' => 'settings',
                    'permission' => null,
                    'children' => [
                        [
                            'title' => 'Profile',
                            'icon' => 'user',
                            'route' => 'user-profile.edit',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Password',
                            'icon' => 'key-round',
                            'route' => 'password.edit',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Two-Factor Auth',
                            'icon' => 'shield-check',
                            'route' => 'two-factor.show',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Appearance',
                            'icon' => 'palette',
                            'route' => 'appearance.edit',
                            'permission' => null,
                        ],
                    ],
                ],
            ],
        ],
        'demo' => [
            'title' => 'Demo',
            'subtitle' => 'Sandbox',
            'icon' => 'flask-conical',
            'route' => 'demo.dashboard',
            'menu' => [
                [
                    'title' => 'Dashboard',
                    'icon' => 'layout-grid',
                    'route' => 'demo.dashboard',
                    'permission' => null,
                ],
            ],
        ],
    ],
];
