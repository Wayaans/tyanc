<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
    Gate::define('manage-users', fn (User $user): bool => $user->hasPermissionTo('manage-users'));

    $permission = Permission::query()->create([
        'name' => 'manage-users',
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

    expect(Gate::forUser($superUser)->allows('manage-users'))->toBeTrue()
        ->and(Gate::forUser($manuseWithoutPermission)->allows('manage-users'))->toBeFalse()
        ->and(Gate::forUser($manuseWithPermission)->allows('manage-users'))->toBeTrue();
});

it('prevents reserved roles from being renamed or deleted', function (): void {
    $reservedRole = Role::query()->create([
        'name' => config('tyanc.reserved_roles.super_admin'),
        'guard_name' => 'web',
        'level' => 100,
    ]);

    expect(fn () => $reservedRole->update(['name' => 'Renamed Role']))->toThrow(LogicException::class);

    $reservedRole = $reservedRole->fresh();

    expect(fn () => $reservedRole?->delete())->toThrow(LogicException::class);
});
