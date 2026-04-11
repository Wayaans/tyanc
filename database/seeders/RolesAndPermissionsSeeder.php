<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSyncSeeder::class);

        DB::transaction(function (): void {
            foreach ($this->roles() as $name => $level) {
                Role::query()->updateOrCreate(
                    [
                        'name' => $name,
                        'guard_name' => 'web',
                    ],
                    [
                        'level' => $level,
                    ],
                );
            }
        });
    }

    /**
     * @return array<string, int>
     */
    private function roles(): array
    {
        return [
            (string) config('tyanc.reserved_roles.super_admin') => 100,
            (string) config('tyanc.reserved_roles.admin') => 90,
            'Operations Lead' => 70,
            'Support Lead' => 40,
            'Demo Analyst' => 20,
            'Access Auditor' => 15,
        ];
    }
}
