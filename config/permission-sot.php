<?php

declare(strict_types=1);

return [
    'actions' => [
        'viewany' => ['label' => 'View any'],
        'view' => ['label' => 'View'],
        'create' => ['label' => 'Create'],
        'update' => ['label' => 'Update'],
        'delete' => ['label' => 'Delete'],
        'manage' => ['label' => 'Manage'],
        'suspend' => ['label' => 'Suspend'],
        'toggle' => ['label' => 'Toggle'],
        'sync' => ['label' => 'Sync'],
        'import' => ['label' => 'Import'],
        'export' => ['label' => 'Export'],
        'upload' => ['label' => 'Upload'],
        'download' => ['label' => 'Download'],
    ],
    'policy_abilities' => [
        'viewAny' => 'viewany',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'restore' => 'manage',
        'forceDelete' => 'manage',
        'suspend' => 'suspend',
        'toggle' => 'toggle',
        'assignPermissions' => 'manage',
        'sync' => 'sync',
    ],
    'manage_implies' => ['viewany', 'view', 'create', 'update', 'delete', 'suspend', 'toggle', 'sync'],
    'apps' => [
        'tyanc' => [
            'label' => 'Tyanc',
            'resources' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'actions' => ['viewany', 'manage'],
                ],
                'apps' => [
                    'label' => 'Apps',
                    'actions' => ['viewany', 'create', 'update', 'delete', 'toggle', 'manage'],
                ],
                'users' => [
                    'label' => 'Users',
                    'actions' => ['viewany', 'view', 'create', 'update', 'delete', 'suspend', 'manage'],
                ],
                'roles' => [
                    'label' => 'Roles',
                    'actions' => ['viewany', 'create', 'update', 'delete', 'manage'],
                ],
                'permissions' => [
                    'label' => 'Permissions',
                    'actions' => ['viewany', 'sync', 'manage'],
                ],
                'access_matrix' => [
                    'label' => 'Access matrix',
                    'actions' => ['viewany', 'update', 'manage'],
                ],
                'settings' => [
                    'label' => 'Settings',
                    'actions' => ['viewany', 'update', 'manage'],
                ],
                'activity_log' => [
                    'label' => 'Activity log',
                    'actions' => ['viewany', 'view', 'manage'],
                ],
                'notifications' => [
                    'label' => 'Notifications',
                    'actions' => ['viewany', 'view', 'manage'],
                ],
            ],
        ],
        'demo' => [
            'label' => 'Demo',
            'resources' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'actions' => ['viewany', 'view', 'manage'],
                ],
                'orders' => [
                    'label' => 'Orders',
                    'actions' => ['viewany', 'view', 'create', 'update', 'delete', 'manage'],
                ],
                'reports' => [
                    'label' => 'Reports',
                    'actions' => ['viewany', 'view', 'export', 'manage'],
                ],
            ],
        ],
    ],
    'legacy' => [
        'manage-users' => 'tyanc.users.manage',
        'manage-settings' => 'tyanc.settings.manage',
        'manage-roles' => 'tyanc.roles.manage',
    ],
];
