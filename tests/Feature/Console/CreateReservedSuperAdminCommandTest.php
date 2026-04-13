<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Role;
use App\Models\User;

it('creates the reserved super admin user through the artisan command', function (): void {
    $this->artisan('tyanc:create-super-admin', [
        '--name' => 'Supa Manuse',
        '--username' => 'supa-manuse',
        '--email' => 'supa@app.com',
        '--password' => 'super-secret-password',
        '--locale' => 'en',
        '--timezone' => 'Asia/Makassar',
    ])->assertSuccessful();

    $user = User::query()->where('reserved_key', 'super_admin')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_reserved)->toBeTrue()
        ->and($user->reserved_key)->toBe('super_admin')
        ->and($user->name)->toBe('Supa Manuse')
        ->and($user->username)->toBe('supa-manuse')
        ->and($user->email)->toBe('supa@app.com')
        ->and($user->hasRole(config('tyanc.reserved_roles.super_admin')))->toBeTrue()
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->exists())->toBeTrue()
        ->and(App::query()->where('key', 'tyanc')->exists())->toBeTrue();
});

it('refuses to create a second reserved super admin user', function (): void {
    User::factory()->create([
        'name' => 'Supa Manuse',
        'username' => 'supa-manuse',
        'email' => 'supa@app.com',
        'is_reserved' => true,
        'reserved_key' => 'super_admin',
    ]);

    $this->artisan('tyanc:create-super-admin', [
        '--password' => 'super-secret-password',
    ])->assertFailed();

    expect(User::query()->where('reserved_key', 'super_admin')->count())->toBe(1);
});

it('refuses to create a second reserved super admin user when the existing one is soft deleted', function (): void {
    $user = User::factory()->create([
        'name' => 'Supa Manuse',
        'username' => 'supa-manuse',
        'email' => 'supa@app.com',
        'is_reserved' => true,
        'reserved_key' => 'super_admin',
    ]);

    $user->delete();

    $this->artisan('tyanc:create-super-admin', [
        '--password' => 'super-secret-password',
    ])->assertFailed();

    expect(User::query()->withTrashed()->where('reserved_key', 'super_admin')->count())->toBe(1);
});
