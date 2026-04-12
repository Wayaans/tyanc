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
                    'title' => 'Files',
                    'icon' => 'folder',
                    'route' => 'tyanc.files.index',
                    'permission' => PermissionKey::tyanc('files', 'viewany'),
                ],
                [
                    'title' => 'Messages',
                    'icon' => 'message-square',
                    'route' => 'tyanc.messages.index',
                    'permission' => PermissionKey::tyanc('messages', 'viewany'),
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
        'cumpu' => [
            'title' => 'Cumpu',
            'subtitle' => 'Approval workspace',
            'icon' => 'shield-check',
            'menu' => [
                [
                    'title' => 'Dashboard',
                    'icon' => 'layout-grid',
                    'route' => 'cumpu.dashboard',
                    'permission' => PermissionKey::cumpu('approvals', 'view'),
                ],
                [
                    'title' => 'My requests',
                    'icon' => 'clock-3',
                    'route' => 'cumpu.approvals.my-requests',
                    'permission' => PermissionKey::cumpu('approvals', 'view'),
                ],
                [
                    'title' => 'Approval inbox',
                    'icon' => 'shield-check',
                    'route' => 'cumpu.approvals.index',
                    'permission' => PermissionKey::cumpu('approvals', 'viewany'),
                ],
                [
                    'title' => 'Approval rules',
                    'icon' => 'settings-2',
                    'route' => 'cumpu.approval-rules.index',
                    'permission' => PermissionKey::cumpu('approval_rules', 'viewany'),
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
