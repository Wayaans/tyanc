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
                    'title' => 'User',
                    'icon' => 'user',
                    'permission' => null,
                ],
                [
                    'title' => 'Role & Permission',
                    'icon' => 'key-round',
                    'permission' => null,
                    'children' => [
                        [
                            'title' => 'Role',
                            'icon' => 'key-round',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Permissions',
                            'icon' => 'key-round',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Level',
                            'icon' => 'key-round',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Group',
                            'icon' => 'key-round',
                            'permission' => null,
                        ],
                    ],
                ],
                [
                    'title' => 'App Settings',
                    'icon' => 'settings',
                    'permission' => null,
                    'children' => [
                        [
                            'title' => 'Application',
                            'icon' => 'settings',
                            'route' => 'tyanc.settings.application.edit',
                            'permission' => null,
                        ],
                        [
                            'title' => 'App Appearance',
                            'icon' => 'palette',
                            'route' => 'tyanc.settings.appearance.edit',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Security',
                            'icon' => 'shield-check',
                            'route' => 'tyanc.settings.security.edit',
                            'permission' => null,
                        ],
                        [
                            'title' => 'Defaults for New Users',
                            'icon' => 'user',
                            'route' => 'tyanc.settings.user-defaults.edit',
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
