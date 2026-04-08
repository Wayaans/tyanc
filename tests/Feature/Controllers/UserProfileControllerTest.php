<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

it('renders profile edit page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/Edit')
            ->has('locales')
            ->has('statuses')
            ->has('timezones')
            ->has('status')
            ->where('mustVerifyEmail', false)
            ->where('canManageStatus', false));
});

it('may update profile information', function (): void {
    $user = User::factory()->create([
        'username' => 'old-name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'New Name',
            'username' => 'new-name',
            'email' => 'new@example.com',
            'city' => 'Denpasar',
        ]);

    $response->assertRedirectToRoute('user-profile.edit');

    $user->refresh()->load('profile');

    expect($user->name)->toBe('New Name')
        ->and($user->username)->toBe('new-name')
        ->and($user->email)->toBe('new@example.com')
        ->and($user->profile)->not->toBeNull()
        ->and($user->profile->city)->toBe('Denpasar');
});

it('keeps email verification when email changes while the feature is disabled', function (): void {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
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
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('prevents a regular user from updating their own status', function (): void {
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'status' => 'suspended',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
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
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'super@example.com',
            'status' => 'suspended',
        ]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->fresh()->status->value)->toBe('suspended');
});

it('requires email', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), []);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('email');
});

it('requires valid email', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'not-an-email',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('email');
});

it('requires unique email except own', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'existing@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('email');
});

it('returns dto json for the profile edit payload', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('user-profile.edit'))
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
                'username',
                'email',
                'profile',
            ],
        ]);
});

it('returns dto json when updating profile information', function (): void {
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
        ->patchJson(route('user-profile.update'), [
            'email' => 'after@example.com',
            'status' => 'suspended',
            'first_name' => 'After',
            'last_name' => 'User',
        ])
        ->assertOk()
        ->assertJsonPath('email', 'after@example.com')
        ->assertJsonPath('status', 'suspended')
        ->assertJsonPath('profile.first_name', 'After');
});

it('allows keeping same email', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionDoesntHaveErrors();
});
