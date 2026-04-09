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
                    'title' => 'Users',
                    'icon' => 'user',
                    'route' => 'tyanc.users.index',
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
                    'title' => 'Activity log',
                    'icon' => 'shield-check',
                    'route' => 'tyanc.activity-log.index',
                    'permission' => null,
                ],
                [
                    'title' => 'App Settings',
                    'icon' => 'settings',
                    'route' => 'tyanc.settings.index',
                    'permission' => null,
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
