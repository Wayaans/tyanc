<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Permissions;

use App\Data\Tyanc\Rbac\PermissionData;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class DeletePermission
{
    public function handle(User $actor, Permission $permission): void
    {
        Gate::forUser($actor)->authorize('delete', $permission);

        $before = PermissionData::fromModel($permission->loadMissing('roles'))->toArray();

        $permission->delete();

        activity('rbac')
            ->performedOn($permission)
            ->causedBy($actor)
            ->event('deleted')
            ->withProperties([
                'old' => $before,
            ])
            ->log('Permission deleted');
    }
}
