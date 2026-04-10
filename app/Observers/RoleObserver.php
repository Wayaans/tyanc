<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Role;
use LogicException;

final class RoleObserver
{
    public function updating(Role $role): void
    {
        $originalName = $role->getOriginal('name');
        $reservedRoles = array_values((array) config('tyanc.reserved_roles', []));

        if (is_string($originalName) && in_array($originalName, $reservedRoles, true) && $role->name !== $originalName) {
            throw new LogicException(__('Reserved roles cannot be renamed.'));
        }
    }

    public function deleting(Role $role): void
    {
        if (in_array($role->name, (array) config('tyanc.reserved_roles', []), true)) {
            throw new LogicException(__('Reserved roles cannot be deleted.'));
        }
    }
}
