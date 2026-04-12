<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Access;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Permissions\SyncRolePermissions;
use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class SyncAccessMatrix
{
    public function __construct(private SyncRolePermissions $permissions) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function handle(User $actor, Role $role, array $permissionNames): Role
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('access_matrix', 'update')),
            AuthorizationException::class,
        );

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
