<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Permission;
use App\Support\Permissions\PermissionKey;
use LogicException;

final class PermissionObserver
{
    public function saving(Permission $permission): void
    {
        if (! PermissionKey::isValid($permission->name)) {
            throw new LogicException(__('Permission names must use the <app>.<resource>.<action> format.'));
        }
    }
}
