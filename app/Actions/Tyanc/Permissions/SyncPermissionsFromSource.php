<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Permissions;

use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncPermissionsFromSource
{
    /**
     * @return array{created: int, existing: int, total: int, permissions: list<string>}
     */
    public function handle(?User $actor = null): array
    {
        $permissionNames = PermissionKey::all();
        $created = 0;
        $existing = 0;

        DB::transaction(function () use ($permissionNames, &$created, &$existing): void {
            foreach ($permissionNames as $permissionName) {
                $permission = Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);

                if ($permission->wasRecentlyCreated) {
                    $created++;

                    continue;
                }

                $existing++;
            }
        });

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($actor instanceof User) {
            activity('rbac')
                ->causedBy($actor)
                ->event('permissions_synced')
                ->withProperties([
                    'created' => $created,
                    'existing' => $existing,
                    'total' => count($permissionNames),
                ])
                ->log('Permissions synced from source');
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'total' => count($permissionNames),
            'permissions' => $permissionNames,
        ];
    }
}
