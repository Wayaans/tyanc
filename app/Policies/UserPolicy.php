<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManageUsers($user) && $this->canManageTarget($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManageUsers($user) && $this->canManageTarget($user, $model);
    }

    public function suspend(User $user, User $model): bool
    {
        return $this->canManageUsers($user)
            && $user->isNot($model)
            && $this->canManageTarget($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canManageUsers($user)
            && $user->isNot($model)
            && $this->canManageTarget($user, $model);
    }

    private function canManageUsers(User $user): bool
    {
        return Permission::query()
            ->where('name', 'manage-users')
            ->where('guard_name', 'web')
            ->exists()
            && $user->hasPermissionTo('manage-users');
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
