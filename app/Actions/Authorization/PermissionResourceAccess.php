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
        if ($this->allowsPermission($user, $permissionName)) {
            return true;
        }

        return collect($this->legacyPermissionsFor($permissionName))
            ->contains(fn (string $legacyPermission): bool => $this->allowsPermission($user, $legacyPermission));
    }

    /**
     * @param  Collection<int, string>  $permissionNames
     */
    public function matchesGrantedPermissions(Collection $permissionNames, string $permissionName): bool
    {
        if ($this->permissionNamesContain($permissionNames, $permissionName)) {
            return true;
        }

        return collect($this->legacyPermissionsFor($permissionName))
            ->contains(fn (string $legacyPermission): bool => $this->permissionNamesContain($permissionNames, $legacyPermission));
    }

    private function allowsPermission(User $user, string $permissionName): bool
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
    private function permissionNamesContain(Collection $permissionNames, string $permissionName): bool
    {
        if ($permissionNames->contains($permissionName)) {
            return true;
        }

        $managePermission = $this->managePermissionFor($permissionName);

        return $managePermission !== null && $permissionNames->contains($managePermission);
    }

    /**
     * @return list<string>
     */
    private function legacyPermissionsFor(string $permissionName): array
    {
        $parsed = PermissionKey::parse($permissionName);

        if ($parsed === null || $parsed['app'] !== 'cumpu') {
            return [];
        }

        if ($parsed['resource'] === 'approvals') {
            return match ($parsed['action']) {
                'view' => [
                    PermissionKey::tyanc('approvals', 'view'),
                    PermissionKey::tyanc('approvals', 'viewany'),
                ],
                default => [PermissionKey::tyanc('approvals', $parsed['action'])],
            };
        }

        return match ([$parsed['resource'], $parsed['action']]) {
            ['dashboard', 'viewany'],
            ['my_requests', 'viewany'],
            ['my_requests', 'view'] => [
                PermissionKey::cumpu('approvals', 'view'),
                PermissionKey::tyanc('approvals', 'view'),
                PermissionKey::tyanc('approvals', 'viewany'),
            ],
            ['approval_inbox', 'viewany'],
            ['all_approvals', 'viewany'] => [
                PermissionKey::cumpu('approvals', 'viewany'),
                PermissionKey::tyanc('approvals', 'viewany'),
            ],
            ['approval_inbox', 'view'],
            ['all_approvals', 'view'] => [
                PermissionKey::cumpu('approvals', 'viewany'),
                PermissionKey::tyanc('approvals', 'viewany'),
            ],
            default => [],
        };
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
