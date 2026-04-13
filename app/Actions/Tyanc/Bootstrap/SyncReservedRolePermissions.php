<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncReservedRolePermissions
{
    /**
     * @return array{roles: array<string, int>, total: int}
     */
    public function handle(): array
    {
        $summary = [];

        DB::transaction(function () use (&$summary): void {
            $superAdminRole = $this->role((string) config('tyanc.reserved_roles.super_admin'));

            if ($superAdminRole instanceof Role) {
                $superAdminRole->syncPermissions([]);
                $summary[$superAdminRole->name] = 0;
            }

            $adminRole = $this->role((string) config('tyanc.reserved_roles.admin'));

            if (! $adminRole instanceof Role) {
                return;
            }

            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', 'like', 'tyanc.%')
                ->get();

            $adminRole->syncPermissions($permissions);
            $summary[$adminRole->name] = $permissions->count();
        });

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'roles' => $summary,
            'total' => array_sum($summary),
        ];
    }

    private function role(string $name): ?Role
    {
        $role = Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        return $role instanceof Role ? $role : null;
    }
}
