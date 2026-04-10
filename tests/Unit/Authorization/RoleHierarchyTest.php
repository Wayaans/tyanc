<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Facades\Gate;

it('compares role levels correctly', function (): void {
    $manuse = Role::query()->create([
        'name' => 'Manuse',
        'guard_name' => 'web',
        'level' => 0,
    ]);

    $manager = Role::query()->create([
        'name' => 'Manager',
        'guard_name' => 'web',
        'level' => 10,
    ]);

    expect($manager->isHigherThan($manuse))->toBeTrue()
        ->and($manuse->isHigherThan($manager))->toBeFalse()
        ->and($manager->isHigherThanOrEqualTo($manuse))->toBeTrue()
        ->and($manuse->isHigherThanOrEqualTo($manuse))->toBeTrue();
});

it('lets Supa Manuse bypass gates while Manuse requires explicit permissions', function (): void {
    $permissionName = PermissionKey::tyanc('users', 'manage');

    Gate::define($permissionName, fn (User $user): bool => $user->hasPermissionTo($permissionName));

    $permission = Permission::query()->firstOrCreate([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);

    $superRole = Role::query()->create([
        'name' => config('tyanc.reserved_roles.super_admin'),
        'guard_name' => 'web',
        'level' => 100,
    ]);

    $manuseRole = Role::query()->create([
        'name' => config('tyanc.reserved_roles.admin'),
        'guard_name' => 'web',
        'level' => 0,
    ]);

    $superUser = User::factory()->create();
    $superUser->assignRole($superRole);

    $manuseWithoutPermission = User::factory()->create();
    $manuseWithoutPermission->assignRole($manuseRole);

    $manuseWithPermission = User::factory()->create();
    $manuseWithPermission->assignRole($manuseRole);
    $manuseWithPermission->givePermissionTo($permission);

    expect(Gate::forUser($superUser)->allows($permissionName))->toBeTrue()
        ->and(Gate::forUser($manuseWithoutPermission)->allows($permissionName))->toBeFalse()
        ->and(Gate::forUser($manuseWithPermission)->allows($permissionName))->toBeTrue();
});

it('prevents immutable roles from being renamed or deleted', function (): void {
    $reservedRole = Role::query()->create([
        'name' => config('tyanc.reserved_roles.super_admin'),
        'guard_name' => 'web',
        'level' => 100,
    ]);

    expect(fn () => $reservedRole->update(['name' => 'Renamed Role']))->toThrow(LogicException::class);

    $reservedRole = $reservedRole->fresh();

    expect(fn () => $reservedRole?->delete())->toThrow(LogicException::class);
});

it('lets the default admin role be updated while still preventing deletion', function (): void {
    $adminRole = Role::query()->create([
        'name' => config('tyanc.reserved_roles.admin'),
        'guard_name' => 'web',
        'level' => 0,
    ]);

    expect($adminRole->update([
        'name' => config('tyanc.reserved_roles.admin'),
        'level' => 5,
    ]))->toBeTrue();

    $adminRole = $adminRole->fresh();

    expect($adminRole?->level)->toBe(5)
        ->and(fn () => $adminRole?->delete())->toThrow(LogicException::class);
});
