<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Seeder;

final class AccessMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AppRegistrySeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        foreach ($this->rolePermissions() as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role instanceof Role) {
                continue;
            }

            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', array_values(array_unique($permissionNames)))
                    ->get(),
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        return [
            (string) config('tyanc.reserved_roles.super_admin') => PermissionKey::all(),
            (string) config('tyanc.reserved_roles.admin') => [
                PermissionKey::tyanc('dashboard', 'manage'),
                PermissionKey::tyanc('apps', 'manage'),
                PermissionKey::tyanc('users', 'manage'),
                PermissionKey::tyanc('files', 'manage'),
                PermissionKey::tyanc('messages', 'manage'),
                PermissionKey::tyanc('roles', 'manage'),
                PermissionKey::tyanc('permissions', 'manage'),
                PermissionKey::tyanc('access_matrix', 'manage'),
                PermissionKey::tyanc('settings', 'manage'),
                PermissionKey::tyanc('activity_log', 'manage'),
                PermissionKey::tyanc('approvals', 'manage'),
                PermissionKey::tyanc('notifications', 'manage'),
                PermissionKey::make('demo', 'dashboard', 'viewany'),
                PermissionKey::make('demo', 'orders', 'viewany'),
                PermissionKey::make('demo', 'reports', 'view'),
            ],
            'Operations Lead' => [
                PermissionKey::tyanc('dashboard', 'viewany'),
                PermissionKey::tyanc('users', 'manage'),
                PermissionKey::tyanc('files', 'viewany'),
                PermissionKey::tyanc('files', 'upload'),
                PermissionKey::tyanc('approvals', 'viewany'),
                PermissionKey::tyanc('approvals', 'approve'),
                PermissionKey::tyanc('approvals', 'reject'),
                PermissionKey::tyanc('activity_log', 'viewany'),
                PermissionKey::tyanc('messages', 'viewany'),
                PermissionKey::tyanc('messages', 'create'),
            ],
            'Support Lead' => [
                PermissionKey::tyanc('dashboard', 'viewany'),
                PermissionKey::tyanc('users', 'viewany'),
                PermissionKey::tyanc('users', 'view'),
                PermissionKey::tyanc('users', 'update'),
                PermissionKey::tyanc('users', 'suspend'),
                PermissionKey::tyanc('messages', 'viewany'),
                PermissionKey::tyanc('messages', 'create'),
                PermissionKey::tyanc('notifications', 'viewany'),
                PermissionKey::tyanc('notifications', 'view'),
            ],
            'Demo Analyst' => [
                PermissionKey::make('demo', 'dashboard', 'viewany'),
                PermissionKey::make('demo', 'orders', 'viewany'),
                PermissionKey::make('demo', 'reports', 'view'),
                PermissionKey::make('demo', 'reports', 'export'),
            ],
            'Access Auditor' => [
                PermissionKey::tyanc('dashboard', 'viewany'),
                PermissionKey::tyanc('apps', 'viewany'),
                PermissionKey::tyanc('roles', 'viewany'),
                PermissionKey::tyanc('permissions', 'viewany'),
                PermissionKey::tyanc('access_matrix', 'viewany'),
                PermissionKey::tyanc('activity_log', 'viewany'),
            ],
        ];
    }
}
