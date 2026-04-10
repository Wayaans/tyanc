<?php

declare(strict_types=1);

use App\Support\Permissions\PermissionKey;

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
                    'permission' => PermissionKey::tyanc('users', 'manage'),
                ],
                [
                    'title' => 'Role & Permission',
                    'icon' => 'key-round',
                    'permission' => null,
                    'children' => [
                        [
                            'title' => 'Apps',
                            'icon' => 'layout-grid',
                            'route' => 'tyanc.apps.index',
                            'permission' => PermissionKey::tyanc('apps', 'manage'),
                        ],
                        [
                            'title' => 'Roles',
                            'icon' => 'key-round',
                            'route' => 'tyanc.roles.index',
                            'permission' => PermissionKey::tyanc('roles', 'manage'),
                        ],
                        [
                            'title' => 'Permissions',
                            'icon' => 'key-round',
                            'route' => 'tyanc.permissions.index',
                            'permission' => PermissionKey::tyanc('permissions', 'manage'),
                        ],
                        [
                            'title' => 'Access matrix',
                            'icon' => 'shield-check',
                            'route' => 'tyanc.access-matrix.index',
                            'permission' => PermissionKey::tyanc('access_matrix', 'manage'),
                        ],
                    ],
                ],
                [
                    'title' => 'Activity log',
                    'icon' => 'shield-check',
                    'route' => 'tyanc.activity-log.index',
                    'permission' => PermissionKey::tyanc('activity_log', 'view'),
                ],
                [
                    'title' => 'App Settings',
                    'icon' => 'settings',
                    'route' => 'tyanc.settings.index',
                    'permission' => PermissionKey::tyanc('settings', 'manage'),
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
                    'permission' => 'demo.dashboard.viewany',
                ],
            ],
        ],
    ],
];
