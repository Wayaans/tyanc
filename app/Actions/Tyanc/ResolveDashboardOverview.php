<?php

declare(strict_types=1);

namespace App\Actions\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Tyanc\Files\MediaFileData;
use App\Enums\UserStatus;
use App\Models\App;
use App\Models\FileLibrary;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class ResolveDashboardOverview
{
    public function __construct(private PermissionResourceAccess $permissionAccess) {}

    /**
     * @return array{
     *     summary: array{module_count: int, healthy_count: int, monitoring_count: int, attention_count: int},
     *     abilities: array{users: bool, roles: bool, permissions: bool, files: bool, apps: bool, messages: bool, activity_log: bool},
     *     modules: list<array{key: string, title: string, value: int, status: string, description: string, metrics: list<array{label: string, value: string|int}>}>,
     *     users: array{
     *         total: int,
     *         active: int,
     *         pending_verification: int,
     *         suspended: int,
     *         banned: int,
     *         verified: int,
     *         two_factor_enabled: int,
     *         recent: array<int, array{id: string, name: string, email: string, avatar: string|null, status: string, roles: array<int, mixed>, created_at: string, last_login_at: string|null}>
     *     },
     *     roles: array{
     *         total: int,
     *         reserved: int,
     *         with_permissions: int,
     *         without_permissions: int,
     *         top: array<int, array{id: int, name: string, level: int, user_count: int, permission_count: int, is_reserved: bool}>
     *     },
     *     permissions: array{
     *         total: int,
     *         source_total: int,
     *         synced: int,
     *         missing: int,
     *         orphaned: int,
     *         top: array<int, array{name: string, app_label: string, action_label: string, resource_label: string, role_count: int, sync_status: string}>
     *     },
     *     files: array{
     *         total: int,
     *         total_size_bytes: int,
     *         total_size_human: string,
     *         recent_uploads: int,
     *         images: int,
     *         documents: int,
     *         recent: array<int, array<string, mixed>>
     *     },
     *     apps: array{
     *         total: int,
     *         enabled: int,
     *         disabled: int,
     *         pages: int,
     *         system: int,
     *         recent: array<int, array{id: string, key: string, label: string, route_prefix: string, enabled: bool, page_count: int, is_system: bool}>
     *     },
     *     alerts: array<int, array{key: string, title: string, description: string, tone: string, target: string}>
     * }
     */
    public function handle(User $actor): array
    {
        $abilities = [
            'users' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('users', 'viewany')),
            'roles' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('roles', 'viewany')),
            'permissions' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('permissions', 'viewany')),
            'files' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('files', 'viewany')),
            'apps' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('apps', 'viewany')),
            'messages' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('messages', 'viewany')),
            'activity_log' => $this->permissionAccess->handle($actor, PermissionKey::tyanc('activity_log', 'viewany')),
        ];

        $users = $this->users();
        $roles = $this->roles();
        $permissions = $this->permissions();
        $files = $this->files();
        $apps = $this->apps();
        $modules = $this->modules($users, $roles, $permissions, $files, $apps);

        return [
            'summary' => [
                'module_count' => count($modules),
                'healthy_count' => collect($modules)->where('status', 'Healthy')->count(),
                'monitoring_count' => collect($modules)->where('status', 'Monitoring')->count(),
                'attention_count' => collect($modules)->where('status', 'Attention')->count(),
            ],
            'abilities' => $abilities,
            'modules' => $modules,
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'files' => $files,
            'apps' => $apps,
            'alerts' => $this->alerts($users, $roles, $permissions, $files, $apps),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     pending_verification: int,
     *     suspended: int,
     *     banned: int,
     *     verified: int,
     *     two_factor_enabled: int,
     *     recent: array<int, array{id: string, name: string, email: string, avatar: string|null, status: string, roles: array<int, mixed>, created_at: string, last_login_at: string|null}>
     * }
     */
    private function users(): array
    {
        $statusCounts = User::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentUsers = User::query()
            ->with('roles:name')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'avatar', 'status', 'last_login_at', 'created_at']);

        return [
            'total' => User::query()->count(),
            'active' => (int) ($statusCounts[UserStatus::Active->value] ?? 0),
            'pending_verification' => (int) ($statusCounts[UserStatus::PendingVerification->value] ?? 0),
            'suspended' => (int) ($statusCounts[UserStatus::Suspended->value] ?? 0),
            'banned' => (int) ($statusCounts[UserStatus::Banned->value] ?? 0),
            'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            'two_factor_enabled' => User::query()->whereNotNull('two_factor_confirmed_at')->count(),
            'recent' => $recentUsers
                ->map(fn (User $user): array => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'status' => $user->status->value,
                    'roles' => $user->roles->pluck('name')->filter()->values()->all(),
                    'created_at' => $user->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     reserved: int,
     *     with_permissions: int,
     *     without_permissions: int,
     *     top: array<int, array{id: int, name: string, level: int, user_count: int, permission_count: int, is_reserved: bool}>
     * }
     */
    private function roles(): array
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('users_count')
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        $reservedRoleNames = collect((array) config('tyanc.immutable_roles', []));

        return [
            'total' => $roles->count(),
            'reserved' => $roles
                ->filter(fn (Role $role): bool => $reservedRoleNames->contains($role->name))
                ->count(),
            'with_permissions' => $roles->where('permissions_count', '>', 0)->count(),
            'without_permissions' => $roles->where('permissions_count', 0)->count(),
            'top' => $roles
                ->take(5)
                ->map(fn (Role $role): array => [
                    'id' => (int) $role->id,
                    'name' => $role->name,
                    'level' => (int) $role->level,
                    'user_count' => (int) ($role->users_count ?? 0),
                    'permission_count' => (int) ($role->permissions_count ?? 0),
                    'is_reserved' => $reservedRoleNames->contains($role->name),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     source_total: int,
     *     synced: int,
     *     missing: int,
     *     orphaned: int,
     *     top: array<int, array{name: string, app_label: string, action_label: string, resource_label: string, role_count: int, sync_status: string}>
     * }
     */
    private function permissions(): array
    {
        $sourcePermissionNames = collect(PermissionKey::all());
        $databasePermissions = Permission::query()
            ->withCount('roles')
            ->orderByDesc('roles_count')
            ->orderBy('name')
            ->get();
        $databasePermissionNames = $databasePermissions->pluck('name');

        return [
            'total' => $databasePermissions->count(),
            'source_total' => $sourcePermissionNames->count(),
            'synced' => $sourcePermissionNames->intersect($databasePermissionNames)->count(),
            'missing' => $sourcePermissionNames->diff($databasePermissionNames)->count(),
            'orphaned' => $databasePermissionNames->diff($sourcePermissionNames)->count(),
            'top' => $databasePermissions
                ->take(5)
                ->map(function (Permission $permission) use ($sourcePermissionNames): array {
                    $parsed = PermissionKey::parse($permission->name);
                    $app = $parsed['app'] ?? 'unknown';
                    $resource = $parsed['resource'] ?? 'general';
                    $action = $parsed['action'] ?? 'manage';
                    $syncStatus = $sourcePermissionNames->contains($permission->name) ? 'synced' : 'orphaned';

                    return [
                        'name' => $permission->name,
                        'app_label' => PermissionKey::appLabel($app),
                        'resource_label' => PermissionKey::resourceLabel($app, $resource),
                        'action_label' => PermissionKey::actionLabel($action),
                        'role_count' => (int) ($permission->roles_count ?? 0),
                        'sync_status' => $syncStatus,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     total_size_bytes: int,
     *     total_size_human: string,
     *     recent_uploads: int,
     *     images: int,
     *     documents: int,
     *     recent: array<int, array<string, mixed>>
     * }
     */
    private function files(): array
    {
        $libraryId = FileLibrary::query()
            ->where('key', FileLibrary::SharedKey)
            ->value('id');

        if (! is_string($libraryId) || $libraryId === '') {
            return [
                'total' => 0,
                'total_size_bytes' => 0,
                'total_size_human' => Number::fileSize(0),
                'recent_uploads' => 0,
                'images' => 0,
                'documents' => 0,
                'recent' => [],
            ];
        }

        $baseQuery = Media::query()
            ->where('model_type', FileLibrary::class)
            ->where('model_id', $libraryId);

        $total = (clone $baseQuery)->count();
        $totalSize = (int) (clone $baseQuery)->sum('size');

        return [
            'total' => $total,
            'total_size_bytes' => $totalSize,
            'total_size_human' => Number::fileSize($totalSize),
            'recent_uploads' => (clone $baseQuery)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'images' => (clone $baseQuery)
                ->where('mime_type', 'like', 'image/%')
                ->count(),
            'documents' => (clone $baseQuery)
                ->where(function ($query): void {
                    $query
                        ->where('mime_type', 'like', 'application/%')
                        ->orWhere('mime_type', 'like', 'text/%');
                })
                ->count(),
            'recent' => (clone $baseQuery)
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (Media $media): array => MediaFileData::fromModel($media)->toArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     enabled: int,
     *     disabled: int,
     *     pages: int,
     *     system: int,
     *     recent: array<int, array{id: string, key: string, label: string, route_prefix: string, enabled: bool, page_count: int, is_system: bool}>
     * }
     */
    private function apps(): array
    {
        $apps = App::query()
            ->withCount('pages')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return [
            'total' => $apps->count(),
            'enabled' => $apps->where('enabled', true)->count(),
            'disabled' => $apps->where('enabled', false)->count(),
            'pages' => (int) $apps->sum('pages_count'),
            'system' => $apps->where('is_system', true)->count(),
            'recent' => $apps
                ->take(5)
                ->map(fn (App $app): array => [
                    'id' => (string) $app->id,
                    'key' => $app->key,
                    'label' => $app->label,
                    'route_prefix' => $app->route_prefix,
                    'enabled' => $app->enabled,
                    'page_count' => (int) ($app->pages_count ?? 0),
                    'is_system' => $app->is_system,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     total: int,
     *     active: int,
     *     pending_verification: int,
     *     suspended: int,
     *     banned: int,
     *     verified: int,
     *     two_factor_enabled: int,
     *     recent: array<int, array{id: string, name: string, email: string, avatar: string|null, status: string, roles: array<int, mixed>, created_at: string, last_login_at: string|null}>
     * }  $users
     * @param  array{
     *     total: int,
     *     reserved: int,
     *     with_permissions: int,
     *     without_permissions: int,
     *     top: array<int, array{id: int, name: string, level: int, user_count: int, permission_count: int, is_reserved: bool}>
     * }  $roles
     * @param  array{
     *     total: int,
     *     source_total: int,
     *     synced: int,
     *     missing: int,
     *     orphaned: int,
     *     top: array<int, array{name: string, app_label: string, action_label: string, resource_label: string, role_count: int, sync_status: string}>
     * }  $permissions
     * @param  array{
     *     total: int,
     *     total_size_bytes: int,
     *     total_size_human: string,
     *     recent_uploads: int,
     *     images: int,
     *     documents: int,
     *     recent: array<int, array<string, mixed>>
     * }  $files
     * @param  array{
     *     total: int,
     *     enabled: int,
     *     disabled: int,
     *     pages: int,
     *     system: int,
     *     recent: array<int, array{id: string, key: string, label: string, route_prefix: string, enabled: bool, page_count: int, is_system: bool}>
     * }  $apps
     * @return list<array{key: string, title: string, value: int, status: string, description: string, metrics: list<array{label: string, value: string|int}>}>
     */
    private function modules(array $users, array $roles, array $permissions, array $files, array $apps): array
    {
        return [
            [
                'key' => 'users',
                'title' => 'Users',
                'value' => $users['total'],
                'status' => $this->usersStatus($users),
                'description' => 'Identity records across the control plane',
                'metrics' => [
                    ['label' => 'Active', 'value' => $users['active']],
                    ['label' => 'Pending', 'value' => $users['pending_verification']],
                ],
            ],
            [
                'key' => 'roles',
                'title' => 'Roles',
                'value' => $roles['total'],
                'status' => $this->rolesStatus($roles),
                'description' => 'Governance roles and access tiers',
                'metrics' => [
                    ['label' => 'Reserved', 'value' => $roles['reserved']],
                    ['label' => 'Without permissions', 'value' => $roles['without_permissions']],
                ],
            ],
            [
                'key' => 'permissions',
                'title' => 'Permissions',
                'value' => $permissions['total'],
                'status' => $this->permissionsStatus($permissions),
                'description' => 'RBAC capability catalog in the database',
                'metrics' => [
                    ['label' => 'Synced', 'value' => $permissions['synced']],
                    ['label' => 'Missing', 'value' => $permissions['missing']],
                ],
            ],
            [
                'key' => 'files',
                'title' => 'Files',
                'value' => $files['total'],
                'status' => $this->filesStatus($files),
                'description' => 'Shared library assets and uploads',
                'metrics' => [
                    ['label' => 'Storage', 'value' => $files['total_size_human']],
                    ['label' => 'Recent uploads', 'value' => $files['recent_uploads']],
                ],
            ],
            [
                'key' => 'apps',
                'title' => 'Apps',
                'value' => $apps['total'],
                'status' => $this->appsStatus($apps),
                'description' => 'Registered apps and governed pages',
                'metrics' => [
                    ['label' => 'Enabled', 'value' => $apps['enabled']],
                    ['label' => 'Pages', 'value' => $apps['pages']],
                ],
            ],
        ];
    }

    /**
     * @param  array{
     *     total: int,
     *     active: int,
     *     pending_verification: int,
     *     suspended: int,
     *     banned: int,
     *     verified: int,
     *     two_factor_enabled: int,
     *     recent: array<int, array{id: string, name: string, email: string, avatar: string|null, status: string, roles: array<int, mixed>, created_at: string, last_login_at: string|null}>
     * }  $users
     */
    private function usersStatus(array $users): string
    {
        if (($users['suspended'] + $users['banned']) > 0) {
            return 'Attention';
        }

        if ($users['pending_verification'] > 0) {
            return 'Monitoring';
        }

        return 'Healthy';
    }

    /**
     * @param  array{
     *     total: int,
     *     reserved: int,
     *     with_permissions: int,
     *     without_permissions: int,
     *     top: array<int, array{id: int, name: string, level: int, user_count: int, permission_count: int, is_reserved: bool}>
     * }  $roles
     */
    private function rolesStatus(array $roles): string
    {
        if ($roles['total'] === 0 || $roles['without_permissions'] > 0) {
            return 'Attention';
        }

        return 'Healthy';
    }

    /**
     * @param  array{
     *     total: int,
     *     source_total: int,
     *     synced: int,
     *     missing: int,
     *     orphaned: int,
     *     top: array<int, array{name: string, app_label: string, action_label: string, resource_label: string, role_count: int, sync_status: string}>
     * }  $permissions
     */
    private function permissionsStatus(array $permissions): string
    {
        if ($permissions['missing'] > 0 || $permissions['orphaned'] > 0) {
            return 'Attention';
        }

        return 'Healthy';
    }

    /**
     * @param  array{
     *     total: int,
     *     total_size_bytes: int,
     *     total_size_human: string,
     *     recent_uploads: int,
     *     images: int,
     *     documents: int,
     *     recent: array<int, array<string, mixed>>
     * }  $files
     */
    private function filesStatus(array $files): string
    {
        if ($files['total'] === 0 || $files['recent_uploads'] === 0) {
            return 'Monitoring';
        }

        return 'Healthy';
    }

    /**
     * @param  array{
     *     total: int,
     *     enabled: int,
     *     disabled: int,
     *     pages: int,
     *     system: int,
     *     recent: array<int, array{id: string, key: string, label: string, route_prefix: string, enabled: bool, page_count: int, is_system: bool}>
     * }  $apps
     */
    private function appsStatus(array $apps): string
    {
        if ($apps['total'] === 0 || $apps['enabled'] === 0) {
            return 'Attention';
        }

        if ($apps['disabled'] > 0) {
            return 'Monitoring';
        }

        return 'Healthy';
    }

    /**
     * @param  array{
     *     total: int,
     *     active: int,
     *     pending_verification: int,
     *     suspended: int,
     *     banned: int,
     *     verified: int,
     *     two_factor_enabled: int,
     *     recent: array<int, array{id: string, name: string, email: string, avatar: string|null, status: string, roles: array<int, mixed>, created_at: string, last_login_at: string|null}>
     * }  $users
     * @param  array{
     *     total: int,
     *     reserved: int,
     *     with_permissions: int,
     *     without_permissions: int,
     *     top: array<int, array{id: int, name: string, level: int, user_count: int, permission_count: int, is_reserved: bool}>
     * }  $roles
     * @param  array{
     *     total: int,
     *     source_total: int,
     *     synced: int,
     *     missing: int,
     *     orphaned: int,
     *     top: array<int, array{name: string, app_label: string, action_label: string, resource_label: string, role_count: int, sync_status: string}>
     * }  $permissions
     * @param  array{
     *     total: int,
     *     total_size_bytes: int,
     *     total_size_human: string,
     *     recent_uploads: int,
     *     images: int,
     *     documents: int,
     *     recent: array<int, array<string, mixed>>
     * }  $files
     * @param  array{
     *     total: int,
     *     enabled: int,
     *     disabled: int,
     *     pages: int,
     *     system: int,
     *     recent: array<int, array{id: string, key: string, label: string, route_prefix: string, enabled: bool, page_count: int, is_system: bool}>
     * }  $apps
     * @return array<int, array{key: string, title: string, description: string, tone: string, target: string}>
     */
    private function alerts(array $users, array $roles, array $permissions, array $files, array $apps): array
    {
        $alerts = Collection::make();

        if (($users['suspended'] + $users['banned']) > 0) {
            $alerts->push([
                'key' => 'flagged-users',
                'title' => sprintf('%d flagged user accounts', $users['suspended'] + $users['banned']),
                'description' => 'Review account restrictions and confirm they are still expected.',
                'tone' => 'danger',
                'target' => 'users',
            ]);
        }

        if ($users['pending_verification'] > 0) {
            $alerts->push([
                'key' => 'pending-verification',
                'title' => sprintf('%d users still need verification', $users['pending_verification']),
                'description' => 'Finish onboarding and access activation for pending accounts.',
                'tone' => 'warning',
                'target' => 'users',
            ]);
        }

        if ($roles['without_permissions'] > 0) {
            $alerts->push([
                'key' => 'roles-without-permissions',
                'title' => sprintf('%d roles have no permissions', $roles['without_permissions']),
                'description' => 'Audit empty roles before they drift away from the intended access model.',
                'tone' => 'warning',
                'target' => 'roles',
            ]);
        }

        if ($permissions['missing'] > 0 || $permissions['orphaned'] > 0) {
            $alerts->push([
                'key' => 'permission-sync-drift',
                'title' => sprintf('%d permission sync issues detected', $permissions['missing'] + $permissions['orphaned']),
                'description' => 'Resolve missing or orphaned permissions to keep RBAC aligned with the source of truth.',
                'tone' => 'danger',
                'target' => 'permissions',
            ]);
        }

        if ($apps['disabled'] > 0) {
            $alerts->push([
                'key' => 'disabled-apps',
                'title' => sprintf('%d apps are disabled', $apps['disabled']),
                'description' => 'Confirm disabled apps and their pages still match the current rollout plan.',
                'tone' => 'info',
                'target' => 'apps',
            ]);
        }

        if ($files['total'] === 0) {
            $alerts->push([
                'key' => 'empty-file-library',
                'title' => 'Shared file library is empty',
                'description' => 'Upload documents, assets, or exports to make the shared library operational.',
                'tone' => 'info',
                'target' => 'files',
            ]);
        }

        return $alerts->all();
    }
}
