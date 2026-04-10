<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\DevelopmentAccessSeeder;

it('creates the development supa manuse account with a full profile and synced registry permissions', function (): void {
    $this->seed(DevelopmentAccessSeeder::class);

    $user = User::query()->with(['profile', 'preference', 'roles'])->where('email', 'supa@app.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('supa-manuse')
        ->and($user->profile)->not->toBeNull()
        ->and($user->profile->first_name)->toBe('Supa')
        ->and($user->profile->last_name)->toBe('Manuse')
        ->and($user->profile->company_name)->toBe('Tyanc')
        ->and($user->preference)->not->toBeNull()
        ->and($user->preference->appearance)->toBe('dark')
        ->and($user->hasRole(config('tyanc.reserved_roles.super_admin')))->toBeTrue()
        ->and(App::query()->where('key', 'tyanc')->where('is_system', true)->exists())->toBeTrue()
        ->and(App::query()->where('key', 'demo')->where('is_system', false)->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', PermissionKey::all())->count())->toBe(count(PermissionKey::all()));
});
