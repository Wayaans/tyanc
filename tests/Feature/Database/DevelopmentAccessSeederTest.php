<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\DevelopmentAccessSeeder;

it('creates the bootstrap reserved users and synced registry permissions', function (): void {
    $this->seed(DevelopmentAccessSeeder::class);

    $supa = User::query()->with(['preference', 'roles'])->where('email', 'supa@app.com')->first();
    $admin = User::query()->with(['preference', 'roles'])->where('email', 'manuse@app.com')->first();

    expect($supa)->not->toBeNull()
        ->and($supa->name)->toBe('Supa Manuse')
        ->and($supa->username)->toBe('supa-manuse')
        ->and($supa->is_reserved)->toBeTrue()
        ->and($supa->reserved_key)->toBe('super_admin')
        ->and($supa->preference)->not->toBeNull()
        ->and($supa->preference->appearance)->toBe('dark')
        ->and($supa->hasRole(config('tyanc.reserved_roles.super_admin')))->toBeTrue()
        ->and($supa->getDirectPermissions())->toHaveCount(0)
        ->and($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Manuse')
        ->and($admin->is_reserved)->toBeTrue()
        ->and($admin->reserved_key)->toBe('admin')
        ->and($admin->hasRole(config('tyanc.reserved_roles.admin')))->toBeTrue()
        ->and(App::query()->where('key', 'tyanc')->where('is_system', true)->exists())->toBeTrue()
        ->and(App::query()->where('key', 'demo')->where('is_system', false)->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', PermissionKey::all())->count())->toBe(count(PermissionKey::all()));
});
