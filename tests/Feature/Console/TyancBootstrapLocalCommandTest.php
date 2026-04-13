<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

it('bootstraps local reserved users and sample users through the local command', function (): void {
    $this->artisan('tyanc:bootstrap-local')
        ->assertSuccessful();

    $supa = User::query()->with(['preference', 'roles'])->where('email', 'supa@app.com')->first();
    $admin = User::query()->with(['preference', 'roles'])->where('email', 'manuse@app.com')->first();
    $nonReservedUsers = User::query()->where('is_reserved', false)->get();

    expect($supa)->not->toBeNull()
        ->and($supa?->reserved_key)->toBe('super_admin')
        ->and($supa?->preference?->appearance)->toBe('dark')
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
        ->and(App::query()->where('key', 'tyanc')->where('is_system', true)->exists())->toBeTrue()
        ->and(App::query()->where('key', 'demo')->where('is_system', false)->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', PermissionKey::all())->count())->toBe(count(PermissionKey::all()))
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->firstOrFail()->permissions()->count())->toBe(0);
});

it('keeps the local bootstrap command idempotent', function (): void {
    $this->artisan('tyanc:bootstrap-local')->assertSuccessful();

    $roleCount = Role::query()->count();
    $userCount = User::query()->count();
    $adminPermissionCount = Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail()->permissions()->count();

    $this->artisan('tyanc:bootstrap-local')->assertSuccessful();

    expect(Role::query()->count())->toBe($roleCount)
        ->and(User::query()->count())->toBe($userCount)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->firstOrFail()->permissions()->count())->toBe(0)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail()->permissions()->count())->toBe($adminPermissionCount);
});
