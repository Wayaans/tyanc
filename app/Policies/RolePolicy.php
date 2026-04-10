<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

final class RolePolicy extends PermissionResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__) && $this->canManageTargetRole($user, $role);
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $this->authorizeAction($user, 'manage') && $this->canManageTargetRole($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->authorizeAbility($user, __FUNCTION__)
            && ! $this->isDeleteProtectedRole($role)
            && $this->canManageTargetRole($user, $role);
    }

    protected function permissionResource(): string
    {
        return 'tyanc.roles';
    }

    private function canManageTargetRole(User $user, Role $role): bool
    {
        if ($this->isImmutableRole($role)) {
            return false;
        }

        $user->loadMissing('roles');

        $actingLevel = $user->roles->max('level');

        if (! is_numeric($actingLevel)) {
            return false;
        }

        return (int) $actingLevel > (int) $role->level;
    }

    private function isImmutableRole(Role $role): bool
    {
        return in_array($role->name, (array) config('tyanc.immutable_roles', []), true);
    }

    private function isDeleteProtectedRole(Role $role): bool
    {
        return in_array($role->name, (array) config('tyanc.undeletable_roles', []), true);
    }
}
