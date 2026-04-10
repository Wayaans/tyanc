<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteRole
{
    public function handle(User $actor, Role $role): void
    {
        Gate::forUser($actor)->authorize('delete', $role);

        $before = RoleData::fromModel($role->loadMissing('permissions'))->toArray();

        $role->delete();

        activity('rbac')
            ->performedOn($role)
            ->causedBy($actor)
            ->event('deleted')
            ->withProperties([
                'old' => $before,
            ])
            ->log('Role deleted');
    }
}
