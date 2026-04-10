<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\DB;

it('normalizes legacy tyanc permissions and preserves role and user assignments', function (): void {
    Permission::query()->whereIn('name', [
        'manage-users',
        'manage-settings',
        'manage-roles',
        ...array_filter(PermissionKey::all(), fn (string $permissionName): bool => str_starts_with($permissionName, 'tyanc.')),
    ])->delete();

    DB::table('permissions')->insert([
        'name' => 'manage-users',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacyPermission = Permission::query()->where('name', 'manage-users')->firstOrFail();

    $role = Role::query()->create([
        'name' => 'Legacy Manager',
        'guard_name' => 'web',
        'level' => 10,
    ]);
    $role->givePermissionTo($legacyPermission);

    $user = User::factory()->create();
    $user->givePermissionTo($legacyPermission);

    $migration = require base_path('database/migrations/2026_04_09_173934_normalize_tyanc_permission_names.php');
    $migration->up();

    $cleanupMigration = require base_path('database/migrations/2026_04_10_091500_remove_unused_default_tyanc_permissions.php');
    $cleanupMigration->up();

    $role->refresh();
    $user->refresh();

    expect(Permission::query()->where('name', 'manage-users')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', PermissionKey::tyanc('users', 'manage'))->exists())->toBeTrue()
        ->and($role->hasPermissionTo(PermissionKey::tyanc('users', 'manage')))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionKey::tyanc('users', 'manage')))->toBeTrue();
});

it('rejects permission names outside the app.resource.action contract', function (): void {
    expect(fn () => Permission::query()->create([
        'name' => 'invalid-permission-name',
        'guard_name' => 'web',
    ]))->toThrow(LogicException::class);

    $permission = Permission::query()->create([
        'name' => 'demo.reports.view',
        'guard_name' => 'web',
    ]);

    expect($permission->name)->toBe('demo.reports.view');
});
