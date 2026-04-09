<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Users;

use App\Actions\CreateUser;
use App\Data\Tyanc\Users\UserFormData;
use App\Models\Role;
use App\Models\User;
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

            $user = $this->users->handle($attributes, (string) $attributes['password']);
            $user->syncRoles($roles);
            $user->syncPermissions($permissions);
            $user->loadMissing('profile', 'roles', 'permissions');

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
