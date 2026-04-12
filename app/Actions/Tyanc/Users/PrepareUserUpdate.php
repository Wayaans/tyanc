<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class PrepareUserUpdate
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function handle(User $actor, User $user, array $attributes): array
    {
        Gate::forUser($actor)->authorize('update', $user);

        $user->loadMissing('roles', 'permissions');

        $roles = $this->names($attributes['roles'] ?? $user->roles->pluck('name')->all());
        $permissions = $this->names($attributes['permissions'] ?? $user->permissions->pluck('name')->all());

        $this->assertAssignableRoles($actor, $roles);
        $this->assertReservedRoleConstraints($actor, $user, $roles);
        $this->assertReservedUserIntegrity($user, $roles, $permissions);
        $this->assertPermissionScope($actor, $permissions);

        return [
            ...$attributes,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    /**
     * @return list<string>
     */
    private function names(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return Collection::make($values)
            ->filter(fn (mixed $value): bool => is_string($value) && mb_trim($value) !== '')
            ->map(fn (string $value): string => mb_trim($value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertAssignableRoles(User $actor, array $roles): void
    {
        if ($roles === [] || $actor->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return;
        }

        $actor->loadMissing('roles');

        $actingLevel = $actor->roles->max('level');

        if (! is_numeric($actingLevel)) {
            return;
        }

        $highestRequestedLevel = Role::query()
            ->whereIn('name', $roles)
            ->max('level');

        if (is_numeric($highestRequestedLevel) && (int) $highestRequestedLevel >= (int) $actingLevel) {
            throw new AuthorizationException(__('You cannot assign roles at or above your own level.'));
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertReservedRoleConstraints(User $actor, User $user, array $roles): void
    {
        $superAdminRole = (string) config('tyanc.reserved_roles.super_admin');

        if (! in_array($superAdminRole, $roles, true)) {
            return;
        }

        if (! $actor->hasRole($superAdminRole)) {
            throw new AuthorizationException(__('Only the reserved super admin user may assign the super admin role.'));
        }

        if ($user->reserved_key !== 'super_admin') {
            throw new AuthorizationException(__('The super admin role may only be assigned to the reserved Supa Manuse user.'));
        }
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    private function assertReservedUserIntegrity(User $user, array $roles, array $permissions): void
    {
        if (! $user->isReserved()) {
            return;
        }

        $requiredRoles = match ($user->reserved_key) {
            'super_admin' => [(string) config('tyanc.reserved_roles.super_admin')],
            'admin' => [(string) config('tyanc.reserved_roles.admin')],
            default => [],
        };

        sort($roles);
        sort($requiredRoles);

        if ($requiredRoles !== [] && $roles !== $requiredRoles) {
            throw new AuthorizationException(__('Reserved users must keep their reserved role assignment.'));
        }

        if ($user->reserved_key === 'super_admin' && $permissions !== []) {
            throw new AuthorizationException(__('The reserved super admin user may not receive direct permissions.'));
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function assertPermissionScope(User $actor, array $permissions): void
    {
        if ($permissions === [] || $actor->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return;
        }

        if ($actor->hasPermissionTo(PermissionKey::tyanc('users', 'manage')) || $actor->hasPermissionTo(PermissionKey::tyanc('permissions', 'manage'))) {
            return;
        }

        $allowedPermissions = $actor->getAllPermissions()->pluck('name')->values();

        $unauthorizedPermissions = Collection::make($permissions)
            ->reject(fn (string $permission): bool => $allowedPermissions->contains($permission))
            ->values();

        if ($unauthorizedPermissions->isNotEmpty()) {
            throw new AuthorizationException(__('You cannot grant permissions outside your own scope.'));
        }
    }
}
