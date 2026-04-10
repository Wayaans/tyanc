<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class StoreRole
{
    /**
     * @param  array{name: string, level: int}  $attributes
     */
    public function handle(User $actor, array $attributes): Role
    {
        Gate::forUser($actor)->authorize('create', Role::class);

        $level = (int) $attributes['level'];
        $this->assertAssignableLevel($actor, $level);

        return DB::transaction(function () use ($actor, $attributes, $level): Role {
            $role = Role::query()->create([
                'name' => mb_trim($attributes['name']),
                'guard_name' => 'web',
                'level' => $level,
            ]);

            $role->load('permissions');

            activity('rbac')
                ->performedOn($role)
                ->causedBy($actor)
                ->event('created')
                ->withProperties([
                    'attributes' => RoleData::fromModel($role)->toArray(),
                ])
                ->log('Role created');

            return $role;
        });
    }

    private function assertAssignableLevel(User $actor, int $level): void
    {
        if ($actor->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return;
        }

        $actor->loadMissing('roles');
        $actingLevel = $actor->roles->max('level');

        if (! is_numeric($actingLevel) || $level >= (int) $actingLevel) {
            throw new AuthorizationException(__('You cannot create roles at or above your own hierarchy level.'));
        }
    }
}
