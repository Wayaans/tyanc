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

        /** @var string $permissionsTable */
        $permissionsTable = $tableNames['permissions'];
        /** @var string $modelHasPermissionsTable */
        $modelHasPermissionsTable = $tableNames['model_has_permissions'];
        /** @var string $roleHasPermissionsTable */
        $roleHasPermissionsTable = $tableNames['role_has_permissions'];

        $legacyPermissions = PermissionKey::legacyMap();
        $sourcePermissions = collect(PermissionKey::all())
            ->filter(fn (string $permissionName): bool => str_starts_with($permissionName, 'tyanc.'))
            ->values()
            ->all();

        DB::transaction(function () use ($permissionsTable, $modelHasPermissionsTable, $roleHasPermissionsTable, $legacyPermissions, $sourcePermissions): void {
            foreach ($legacyPermissions as $legacyName => $normalizedName) {
                $this->renamePermission(
                    permissionsTable: $permissionsTable,
                    modelHasPermissionsTable: $modelHasPermissionsTable,
                    roleHasPermissionsTable: $roleHasPermissionsTable,
                    legacyName: $legacyName,
                    normalizedName: $normalizedName,
                );
            }

            foreach ($sourcePermissions as $permissionName) {
                $existingPermission = DB::table($permissionsTable)
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->first();

                if ($existingPermission !== null) {
                    continue;
                }

                DB::table($permissionsTable)->insert([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        /** @var string $permissionsTable */
        $permissionsTable = $tableNames['permissions'];

        DB::transaction(function () use ($permissionsTable): void {
            foreach (array_flip(PermissionKey::legacyMap()) as $normalizedName => $legacyName) {
                DB::table($permissionsTable)
                    ->where('name', $normalizedName)
                    ->where('guard_name', 'web')
                    ->update([
                        'name' => $legacyName,
                        'updated_at' => now(),
                    ]);
            }
        });

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function renamePermission(
        string $permissionsTable,
        string $modelHasPermissionsTable,
        string $roleHasPermissionsTable,
        string $legacyName,
        string $normalizedName,
    ): void {
        $legacyPermission = DB::table($permissionsTable)
            ->where('name', $legacyName)
            ->where('guard_name', 'web')
            ->first();

        if ($legacyPermission === null) {
            return;
        }

        $normalizedPermission = DB::table($permissionsTable)
            ->where('name', $normalizedName)
            ->where('guard_name', 'web')
            ->first();

        if ($normalizedPermission === null) {
            DB::table($permissionsTable)
                ->where('id', $legacyPermission->id)
                ->update([
                    'name' => $normalizedName,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table($modelHasPermissionsTable)
            ->where('permission_id', $legacyPermission->id)
            ->update(['permission_id' => $normalizedPermission->id]);

        DB::table($roleHasPermissionsTable)
            ->where('permission_id', $legacyPermission->id)
            ->update(['permission_id' => $normalizedPermission->id]);

        DB::table($permissionsTable)
            ->where('id', $legacyPermission->id)
            ->delete();
    }
};
