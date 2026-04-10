<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

abstract class PermissionResourcePolicy
{
    abstract protected function permissionResource(): string;

    protected function authorizeAbility(User $user, string $ability): bool
    {
        $access = resolve(PermissionResourceAccess::class);

        return array_any(
            $this->permissionNamesForAbility($ability),
            fn (string $permissionName): bool => $access->handle($user, $permissionName),
        );
    }

    protected function authorizeAction(User $user, string $action): bool
    {
        return resolve(PermissionResourceAccess::class)->handle($user, $this->permissionName($action));
    }

    /**
     * @return list<string>
     */
    protected function permissionNamesForAbility(string $ability): array
    {
        return PermissionKey::namesForAbility($this->permissionResource(), $ability);
    }

    protected function permissionName(string $action): string
    {
        [$app, $resource] = array_pad(explode('.', $this->permissionResource(), 2), 2, null);

        if (! is_string($app) || ! is_string($resource) || $app === '' || $resource === '') {
            return '';
        }

        return PermissionKey::make($app, $resource, $action);
    }
}
