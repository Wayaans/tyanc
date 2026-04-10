<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\App;
use App\Models\User;

final class AppPolicy extends PermissionResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function update(User $user, App $app): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function toggle(User $user, App $app): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__) && ! $app->isSystem();
    }

    public function delete(User $user, App $app): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__) && ! $app->isSystem();
    }

    protected function permissionResource(): string
    {
        return 'tyanc.apps';
    }
}
