<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Access;

use App\Actions\Tyanc\Permissions\SyncRolePermissions;
use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SyncAccessMatrix
{
    public function __construct(private SyncRolePermissions $permissions) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function handle(User $actor, Role $role, array $permissionNames): Role
    {
        Gate::forUser($actor)->authorize(PermissionKey::tyanc('access_matrix', 'manage'));

        $before = RoleData::fromModel($role->fresh(['permissions']))->toArray();

        return DB::transaction(function () use ($actor, $role, $permissionNames, $before): Role {
            $this->permissions->handle($actor, $role, $permissionNames);
            $role->load('permissions');

            activity('rbac')
                ->performedOn($role)
                ->causedBy($actor)
                ->event('access_matrix_synced')
                ->withProperties([
                    'old' => $before,
                    'attributes' => RoleData::fromModel($role)->toArray(),
                ])
                ->log('Access matrix synced');

            return $role;
        });
    }
}
