<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Permissions;

use App\Data\Tyanc\Rbac\PermissionData;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdatePermission
{
    /**
     * @param  array{name: string, roles?: list<string>}  $attributes
     */
    public function handle(User $actor, Permission $permission, array $attributes): Permission
    {
        Gate::forUser($actor)->authorize('update', $permission);

        $name = mb_strtolower(mb_trim($attributes['name']));
        $roles = $this->roleNames($attributes['roles'] ?? []);
        $before = PermissionData::fromModel($permission->fresh(['roles']))->toArray();

        $this->assertPermissionScope($actor, $name);
        $this->assertRoleScope($actor, $roles);

        return DB::transaction(function () use ($actor, $permission, $name, $roles, $before): Permission {
            $permission->forceFill([
                'name' => $name,
            ])->save();

            $permission->syncRoles($roles);
            $permission->load('roles');

            activity('rbac')
                ->performedOn($permission)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => PermissionData::fromModel($permission)->toArray(),
                ])
                ->log('Permission updated');

            return $permission;
        });
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertRoleScope(User $actor, array $roles): void
    {
        if ($roles === [] || $actor->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return;
        }

        $actor->loadMissing('roles');
        $actingLevel = $actor->roles->max('level');
        $highestRequestedLevel = Role::query()->whereIn('name', $roles)->max('level');

        if (! is_numeric($actingLevel) || (is_numeric($highestRequestedLevel) && (int) $highestRequestedLevel >= (int) $actingLevel)) {
            throw new AuthorizationException(__('You cannot assign permissions to roles at or above your own hierarchy level.'));
        }
    }

    private function assertPermissionScope(User $actor, string $permissionName): void
    {
        if ($actor->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return;
        }

        $allowedApps = $actor->getAllPermissions()
            ->pluck('name')
            ->map(fn (string $name): string => explode('.', $name)[0])
            ->unique()
            ->values();

        $appKey = explode('.', $permissionName)[0] ?? null;

        if (! is_string($appKey) || $allowedApps->doesntContain($appKey)) {
            throw new AuthorizationException(__('You cannot update permissions outside your own app scope.'));
        }
    }

    /**
     * @return list<string>
     */
    private function roleNames(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        return Collection::make($roles)
            ->filter(fn (mixed $role): bool => is_string($role) && mb_trim($role) !== '')
            ->map(fn (string $role): string => mb_trim($role))
            ->unique()
            ->values()
            ->all();
    }
}
