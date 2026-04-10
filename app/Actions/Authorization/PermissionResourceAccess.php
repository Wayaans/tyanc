<?php

declare(strict_types=1);

namespace App\Actions\Authorization;

use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class PermissionResourceAccess
{
    public function handle(User $user, string $permissionName): bool
    {
        if (Gate::forUser($user)->allows($permissionName)) {
            return true;
        }

        $managePermission = $this->managePermissionFor($permissionName);

        return $managePermission !== null
            && Gate::forUser($user)->allows($managePermission);
    }

    /**
     * @param  Collection<int, string>  $permissionNames
     */
    public function matchesGrantedPermissions(Collection $permissionNames, string $permissionName): bool
    {
        if ($permissionNames->contains($permissionName)) {
            return true;
        }

        $managePermission = $this->managePermissionFor($permissionName);

        return $managePermission !== null && $permissionNames->contains($managePermission);
    }

    private function managePermissionFor(string $permissionName): ?string
    {
        $parsed = PermissionKey::parse($permissionName);

        if ($parsed === null) {
            return null;
        }

        if (! in_array($parsed['action'], PermissionKey::manageImpliedActions(), true)) {
            return null;
        }

        return PermissionKey::make($parsed['app'], $parsed['resource'], 'manage');
    }
}
