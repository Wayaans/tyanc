<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = (string) config('permission.table_names.roles');
        $adminRoleName = (string) config('tyanc.reserved_roles.admin');

        DB::table($rolesTable)
            ->where('name', $adminRoleName)
            ->where('guard_name', 'web')
            ->update([
                'level' => 90,
                'updated_at' => now(),
            ]);

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $rolesTable = (string) config('permission.table_names.roles');
        $adminRoleName = (string) config('tyanc.reserved_roles.admin');

        DB::table($rolesTable)
            ->where('name', $adminRoleName)
            ->where('guard_name', 'web')
            ->update([
                'level' => 0,
                'updated_at' => now(),
            ]);

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
