<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Actions\Tyanc\Permissions\SyncRolePermissions;
use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class AssignPermissionsToRole
{
    public function __construct(private SyncRolePermissions $permissions) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function handle(User $actor, Role $role, array $permissionNames): Role
    {
        Gate::forUser($actor)->authorize('assignPermissions', $role);

        $before = RoleData::fromModel($role->fresh(['permissions']))->toArray();

        return DB::transaction(function () use ($actor, $role, $permissionNames, $before): Role {
            $this->permissions->handle($actor, $role, $permissionNames);
            $role->load('permissions');

            activity('rbac')
                ->performedOn($role)
                ->causedBy($actor)
                ->event('permissions_assigned')
                ->withProperties([
                    'old' => $before,
                    'attributes' => RoleData::fromModel($role)->toArray(),
                ])
                ->log('Role permissions assigned');

            return $role;
        });
    }
}
