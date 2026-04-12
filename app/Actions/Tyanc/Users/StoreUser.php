<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\CreateUser;
use App\Data\Tyanc\Users\UserFormData;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class StoreUser
{
    public function __construct(private CreateUser $users) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return DB::transaction(function () use ($actor, $attributes): User {
            $roles = $this->names($attributes['roles'] ?? []);
            $permissions = $this->names($attributes['permissions'] ?? []);

            $this->assertAssignableRoles($actor, $roles);
            $this->assertReservedRoleConstraints($actor, null, $roles);
            $this->assertPermissionScope($actor, $permissions);

            $user = $this->users->handle($attributes, (string) $attributes['password']);
            $user->syncRoles($roles);
            $user->syncPermissions($permissions);
            $user->loadMissing('roles', 'permissions');

            activity('users')
                ->performedOn($user)
                ->causedBy($actor)
                ->event('created')
                ->withProperties([
                    'attributes' => UserFormData::fromModel($user)->toArray(),
                ])
                ->log('User created');

            return $user;
        });
    }

    /**
     * @return array<int, string>
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
     * @param  array<int, string>  $roles
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
     * @param  array<int, string>  $roles
     */
    private function assertReservedRoleConstraints(User $actor, ?User $user, array $roles): void
    {
        $superAdminRole = (string) config('tyanc.reserved_roles.super_admin');

        if (! in_array($superAdminRole, $roles, true)) {
            return;
        }

        if (! $actor->hasRole($superAdminRole)) {
            throw new AuthorizationException(__('Only the reserved super admin user may assign the super admin role.'));
        }

        if (! $user instanceof User || $user->reserved_key !== 'super_admin') {
            throw new AuthorizationException(__('The super admin role may only be assigned to the reserved Supa Manuse user.'));
        }
    }

    /**
     * @param  array<int, string>  $permissions
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
