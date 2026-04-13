<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Actions\Tyanc\Permissions\SyncPermissionsFromSource;

final readonly class RunProductionBootstrap
{
    public function __construct(
        private SyncPermissionsFromSource $permissions,
        private SyncConfiguredApps $configuredApps,
        private SyncReservedRoles $reservedRoles,
        private SyncReservedRolePermissions $reservedRolePermissions,
        private ResolveBootstrapStatus $bootstrapStatus,
    ) {}

    /**
     * @return array{
     *     permissions: array{created: int, existing: int, total: int, permissions: array<int, string>},
     *     apps: array{created: int, existing: int, synced: int, skipped: int, apps: list<string>},
     *     roles: array{created: int, existing: int, roles: list<string>},
     *     role_permissions: array{roles: array<string, int>, total: int},
     *     status: array{ready: bool, missing: list<string>, warnings: list<string>}
     * }
     */
    public function handle(): array
    {
        $result = [
            'permissions' => $this->permissions->handle(),
            'apps' => $this->configuredApps->handle(),
            'roles' => $this->reservedRoles->handle(),
            'role_permissions' => $this->reservedRolePermissions->handle(),
        ];

        $result['status'] = $this->bootstrapStatus->handle();

        return $result;
    }
}
