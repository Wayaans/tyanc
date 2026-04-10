<?php

declare(strict_types=1);

use App\Support\Permissions\PermissionKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $knownPermissionNames = collect(PermissionKey::all());

        if ($knownPermissionNames->isEmpty()) {
            return;
        }

        $permissionsTable = (string) $tableNames['permissions'];
        $modelHasPermissionsTable = (string) $tableNames['model_has_permissions'];
        $roleHasPermissionsTable = (string) $tableNames['role_has_permissions'];

        DB::transaction(function () use ($permissionsTable, $modelHasPermissionsTable, $roleHasPermissionsTable, $knownPermissionNames): void {
            $permissions = DB::table($permissionsTable)
                ->where('guard_name', 'web')
                ->where('name', 'like', 'tyanc.%')
                ->get(['id', 'name']);

            foreach ($permissions as $permission) {
                if ($knownPermissionNames->contains($permission->name)) {
                    continue;
                }

                $hasAssignments = DB::table($roleHasPermissionsTable)
                    ->where('permission_id', $permission->id)
                    ->exists()
                    || DB::table($modelHasPermissionsTable)
                        ->where('permission_id', $permission->id)
                        ->exists();

                if ($hasAssignments) {
                    continue;
                }

                DB::table($permissionsTable)
                    ->where('id', $permission->id)
                    ->delete();
            }
        });

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally empty. Use the source-of-truth sync to recreate missing permissions.
    }
};
