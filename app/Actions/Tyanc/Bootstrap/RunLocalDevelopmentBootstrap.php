<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Actions\Tyanc\Users\EnsureReservedUser;
use RuntimeException;

final readonly class RunLocalDevelopmentBootstrap
{
    public function __construct(
        private RunProductionBootstrap $productionBootstrap,
        private EnsureReservedUser $reservedUsers,
        private SyncLocalSampleUsers $localSampleUsers,
    ) {}

    /**
     * @return array{
     *     production: array{
     *         permissions: array{created: int, existing: int, total: int, permissions: array<int, string>},
     *         apps: array{created: int, existing: int, synced: int, skipped: int, apps: list<string>},
     *         roles: array{created: int, existing: int, roles: list<string>},
     *         role_permissions: array{roles: array<string, int>, total: int},
     *         status: array{ready: bool, missing: list<string>, warnings: list<string>}
     *     },
     *     reserved_users: list<string>,
     *     sample_users: array{total: int, users: list<string>}
     * }
     */
    public function handle(): array
    {
        throw_unless(app()->environment(['local', 'testing']), RuntimeException::class, 'The local Tyanc bootstrap is only available in local and testing environments.');

        $production = $this->productionBootstrap->handle();

        throw_unless($production['status']['ready'], RuntimeException::class, 'The production-safe Tyanc bootstrap must be ready before local reserved and sample users are created.');

        $password = $this->localReservedPassword();

        $superAdmin = $this->reservedUsers->handle('super_admin', [
            'password' => $password,
        ]);

        $admin = $this->reservedUsers->handle('admin', [
            'password' => $password,
        ]);

        return [
            'production' => $production,
            'reserved_users' => [$superAdmin->email, $admin->email],
            'sample_users' => $this->localSampleUsers->handle(),
        ];
    }

    private function localReservedPassword(): string
    {
        $password = mb_trim((string) config('tyanc.local_bootstrap.reserved_password', 'password'));

        return $password === '' ? 'password' : $password;
    }
}
