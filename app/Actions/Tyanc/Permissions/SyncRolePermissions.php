<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Permissions;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class SyncRolePermissions
{
    /**
     * @param  array<int, string>  $permissionNames
     */
    public function handle(User $actor, Role $role, array $permissionNames): Role
    {
        Gate::forUser($actor)->authorize('assignPermissions', $role);

        $normalizedPermissionNames = Collection::make($permissionNames)
            ->filter(fn (string $permission): bool => mb_trim($permission) !== '')
            ->map(fn (string $permission): string => mb_trim($permission))
            ->unique()
            ->values()
            ->all();

        $this->assertPermissionScope($role, $normalizedPermissionNames);
        $this->ensurePermissionRecords($normalizedPermissionNames);

        $role->syncPermissions($normalizedPermissionNames);
        $role->loadMissing('permissions');

        return $role;
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    private function assertPermissionScope(Role $role, array $permissionNames): void
    {
        $currentPermissions = $role->loadMissing('permissions')->permissions->pluck('name');

        $unknownPermissions = Collection::make($permissionNames)
            ->reject(fn (string $permission): bool => PermissionKey::existsInSource($permission) || $currentPermissions->contains($permission))
            ->values();

        if ($unknownPermissions->isNotEmpty()) {
            throw new AuthorizationException(__('You cannot grant permissions outside the configured permission source of truth.'));
        }
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    private function ensurePermissionRecords(array $permissionNames): void
    {
        $sourcePermissionNames = Collection::make($permissionNames)
            ->filter(fn (string $permission): bool => PermissionKey::existsInSource($permission))
            ->values();

        if ($sourcePermissionNames->isEmpty()) {
            return;
        }

        $existingPermissionNames = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $sourcePermissionNames->all())
            ->pluck('name');

        $sourcePermissionNames
            ->reject(fn (string $permission): bool => $existingPermissionNames->contains($permission))
            ->each(fn (string $permission): Permission => Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]));
    }
}
