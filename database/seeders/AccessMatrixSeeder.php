<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class AccessMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AppRegistrySeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        foreach ($this->rolePermissions() as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role instanceof Role) {
                continue;
            }

            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', array_values(array_unique($permissionNames)))
                    ->get(),
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        return [
            (string) config('tyanc.reserved_roles.super_admin') => [],
            (string) config('tyanc.reserved_roles.admin') => Permission::query()
                ->where('guard_name', 'web')
                ->where('name', 'like', 'tyanc.%')
                ->pluck('name')
                ->values()
                ->all(),
        ];
    }
}
