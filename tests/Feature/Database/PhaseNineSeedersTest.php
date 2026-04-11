<?php

declare(strict_types=1);

use App\Models\ApprovalRequest;
use App\Models\Conversation;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

it('seeds the production bootstrap users and roles without demo records', function (): void {
    $this->seed(DatabaseSeeder::class);

    $supa = User::query()->where('email', 'supa@app.com')->first();
    $admin = User::query()->where('email', 'manuse@app.com')->first();
    $nonReservedUsers = User::query()->where('is_reserved', false)->get();

    expect($supa)->not->toBeNull()
        ->and($supa?->reserved_key)->toBe('super_admin')
        ->and($supa?->hasRole((string) config('tyanc.reserved_roles.super_admin')))->toBeTrue()
        ->and($supa?->getDirectPermissions())->toHaveCount(0)
        ->and($admin)->not->toBeNull()
        ->and($admin?->reserved_key)->toBe('admin')
        ->and($admin?->hasRole((string) config('tyanc.reserved_roles.admin')))->toBeTrue()
        ->and(User::query()->count())->toBe(5)
        ->and($nonReservedUsers)->toHaveCount(3)
        ->and($nonReservedUsers->every(fn (User $user): bool => $user->locale === 'id'))->toBeTrue()
        ->and($nonReservedUsers->every(fn (User $user): bool => $user->roles()->count() === 0))->toBeTrue()
        ->and($nonReservedUsers->every(fn (User $user): bool => $user->getDirectPermissions()->count() === 0))->toBeTrue()
        ->and(Role::query()->orderByDesc('level')->pluck('name')->all())->toBe([
            (string) config('tyanc.reserved_roles.super_admin'),
            (string) config('tyanc.reserved_roles.admin'),
        ])
        ->and(ImportRun::query()->count())->toBe(0)
        ->and(ApprovalRequest::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0);
});

it('keeps role and access-matrix seeders idempotent', function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        AccessMatrixSeeder::class,
    ]);

    $roleCount = Role::query()->count();
    $admin = Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail();
    $adminPermissionCount = $admin->permissions()->count();

    $this->seed([
        RolesAndPermissionsSeeder::class,
        AccessMatrixSeeder::class,
    ]);

    expect(Role::query()->count())->toBe($roleCount)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->firstOrFail()->permissions()->count())->toBe(0)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail()->permissions()->count())->toBe($adminPermissionCount);
});
