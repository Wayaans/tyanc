<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\UpdateUser as UpdateManagedUser;
use App\Data\Tyanc\Users\UserFormData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateUser
{
    public function __construct(private UpdateManagedUser $users) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, User $user, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        return DB::transaction(function () use ($actor, $user, $attributes): User {
            $roles = $this->names($attributes['roles'] ?? []);
            $permissions = $this->names($attributes['permissions'] ?? []);
            $before = UserFormData::fromModel($user->fresh(['profile', 'roles', 'permissions']))->toArray();

            $this->assertAssignableRoles($actor, $roles);

            $updatedUser = $this->users->handle($user, $attributes);

            if (is_string($attributes['password'] ?? null) && mb_trim($attributes['password']) !== '') {
                $updatedUser->forceFill([
                    'password' => $attributes['password'],
                ])->save();
            }

            $updatedUser->syncRoles($roles);
            $updatedUser->syncPermissions($permissions);
            $updatedUser->loadMissing('profile', 'roles', 'permissions');

            activity('users')
                ->performedOn($updatedUser)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => UserFormData::fromModel($updatedUser)->toArray(),
                ])
                ->log('User updated');

            return $updatedUser;
        });
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
}
