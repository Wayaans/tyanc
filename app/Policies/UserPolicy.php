<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy extends PermissionResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function view(User $user, User $model): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__) && $this->canManageTarget($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function update(User $user, User $model): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__) && $this->canManageTarget($user, $model);
    }

    public function suspend(User $user, User $model): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__)
            && $user->isNot($model)
            && $this->canManageTarget($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__)
            && $user->isNot($model)
            && $this->canManageTarget($user, $model);
    }

    protected function permissionResource(): string
    {
        return 'tyanc.users';
    }

    private function canManageTarget(User $user, User $model): bool
    {
        $user->loadMissing('roles');
        $model->loadMissing('roles');

        $actingLevel = $user->roles->max('level');
        $targetLevel = $model->roles->max('level');

        if (! is_numeric($actingLevel) || ! is_numeric($targetLevel)) {
            return true;
        }

        return (int) $actingLevel > (int) $targetLevel;
    }
}
