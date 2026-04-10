<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class PermissionPolicy extends PermissionResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function sync(User $user): bool
    {
        return $this->authorizeAction($user, 'sync');
    }

    protected function permissionResource(): string
    {
        return 'tyanc.permissions';
    }
}
