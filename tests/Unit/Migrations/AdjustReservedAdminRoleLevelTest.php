<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('raises the reserved admin role level so baseline admins can manage lower-level roles', function (): void {
    $rolesTable = (string) config('permission.table_names.roles');
    $adminRoleName = (string) config('tyanc.reserved_roles.admin');

    DB::table($rolesTable)->insert([
        'name' => $adminRoleName,
        'guard_name' => 'web',
        'level' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_04_10_233904_adjust_reserved_admin_role_level.php');
    $migration->up();

    expect(
        (int) DB::table($rolesTable)
            ->where('name', $adminRoleName)
            ->where('guard_name', 'web')
            ->value('level'),
    )->toBe(90);

    $migration->down();

    expect(
        (int) DB::table($rolesTable)
            ->where('name', $adminRoleName)
            ->where('guard_name', 'web')
            ->value('level'),
    )->toBe(0);
});
