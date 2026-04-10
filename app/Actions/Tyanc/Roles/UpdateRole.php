<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateRole
{
    /**
     * @param  array{name: string, level: int}  $attributes
     */
    public function handle(User $actor, Role $role, array $attributes): Role
    {
        Gate::forUser($actor)->authorize('update', $role);

        $level = (int) $attributes['level'];
        $this->assertAssignableLevel($actor, $level);
        $before = RoleData::fromModel($role->fresh(['permissions']))->toArray();

        return DB::transaction(function () use ($actor, $role, $attributes, $level, $before): Role {
            $role->forceFill([
                'name' => mb_trim($attributes['name']),
                'level' => $level,
            ])->save();

            $role->load('permissions');

            activity('rbac')
                ->performedOn($role)
                ->causedBy($actor)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => RoleData::fromModel($role)->toArray(),
                ])
                ->log('Role updated');

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
            throw new AuthorizationException(__('You cannot assign roles at or above your own hierarchy level.'));
        }
    }
}
