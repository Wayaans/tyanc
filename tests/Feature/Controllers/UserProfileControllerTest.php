<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

it('renders account settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('settings.account.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Account')
            ->has('locales')
            ->has('statuses')
            ->has('timezones')
            ->has('status')
            ->where('mustVerifyEmail', false)
            ->where('canManageStatus', false));
});

it('may update account information', function (): void {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'username' => 'old-name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('settings.account.edit')
        ->patch(route('settings.account.update'), [
            'name' => 'New Name',
            'username' => 'new-name',
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('settings.account.edit');

    expect($user->fresh()->name)->toBe('New Name')
        ->and($user->fresh()->username)->toBe('new-name')
        ->and($user->fresh()->email)->toBe('new@example.com');
});

it('stores an uploaded avatar when updating the account', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'avatar' => null,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('settings.account.edit')
        ->patch(route('settings.account.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 256, 256),
        ]);

    $response->assertRedirectToRoute('settings.account.edit');

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists((string) $user->avatar);

    $this->actingAs($user)
        ->get(route('settings.account.edit'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'auth.user.avatar',
                '/storage/'.mb_ltrim((string) $user->avatar, '/'),
            ));
});

it('keeps email verification when email changes while the feature is disabled', function (): void {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('settings.account.edit')
        ->patch(route('settings.account.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('settings.account.edit');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('resets email verification and sends a notification when the feature is enabled', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [
            Features::registration(),
            Features::emailVerification(),
        ],
    ]);

    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('settings.account.edit')
        ->patch(route('settings.account.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('settings.account.edit');

    expect($user->fresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('prevents a regular user from updating their own status', function (): void {
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('settings.account.edit')
        ->patch(route('settings.account.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => 'suspended',
        ]);

    $response->assertRedirectToRoute('settings.account.edit')
        ->assertSessionHasErrors('status');

    expect($user->fresh()->status->value)->toBe('active');
});

it('allows a Supa Manuse user to update status', function (): void {
    $role = Role::query()->create([
        'name' => config('tyanc.reserved_roles.super_admin'),
        'guard_name' => 'web',
        'level' => 100,
    ]);

    $user = User::factory()->create([
        'status' => 'active',
        'email' => 'super@example.com',
    ]);
    $user->assignRole($role);

    $response = $this->actingAs($user)
        ->fromRoute('settings.account.edit')
        ->patch(route('settings.account.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'super@example.com',
            'status' => 'suspended',
        ]);

    $response->assertRedirectToRoute('settings.account.edit');

    expect($user->fresh()->status->value)->toBe('suspended');
});

it('returns dto json for the account edit payload', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('settings.account.edit'))
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.username', $user->username)
        ->assertJsonStructure([
            'status',
            'mustVerifyEmail',
            'canManageStatus',
            'locales',
            'statuses',
            'timezones',
            'user' => [
                'id',
                'name',
                'username',
                'email',
                'is_reserved',
                'reserved_key',
            ],
        ]);
});

it('returns dto json when updating account information', function (): void {
    $role = Role::query()->create([
        'name' => config('tyanc.reserved_roles.super_admin'),
        'guard_name' => 'web',
        'level' => 100,
    ]);

    $user = User::factory()->create([
        'email' => 'before@example.com',
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->patchJson(route('settings.account.update'), [
            'name' => 'After User',
            'username' => $user->username,
            'email' => 'after@example.com',
            'status' => 'suspended',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'After User')
        ->assertJsonPath('email', 'after@example.com')
        ->assertJsonPath('status', 'suspended');
});
