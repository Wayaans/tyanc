<?php

declare(strict_types=1);

use App\Models\User;

it('creates a profile record when a user registers', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Tyanc User',
        'email' => 'tyanc@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertRedirectToRoute('dashboard');

    $user = User::query()->where('email', 'tyanc@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->profile)->not->toBeNull()
        ->and($user->profile->first_name)->toBe('Tyanc')
        ->and($user->profile->last_name)->toBe('User');
});

it('updates user and profile fields together', function (): void {
    $user = User::factory()->create([
        'username' => 'tyanc-user',
        'email' => 'tyanc@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('user-profile.update'), [
            'username' => 'updated-user',
            'email' => 'updated@example.com',
            'timezone' => 'Asia/Makassar',
            'locale' => 'en',
            'first_name' => 'Updated',
            'last_name' => 'User',
            'city' => 'Denpasar',
            'social_links' => [
                'github' => 'https://github.com/updated-user',
            ],
        ])
        ->assertRedirectToRoute('user-profile.edit');

    $user->refresh()->load('profile');

    expect($user->username)->toBe('updated-user')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->timezone)->toBe('Asia/Makassar')
        ->and($user->name)->toBe('Updated User')
        ->and($user->profile->city)->toBe('Denpasar')
        ->and($user->profile->social_links)->toBe([
            'github' => 'https://github.com/updated-user',
        ]);
});

it('soft deletes the authenticated user account', function (): void {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->delete(route('user.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirectToRoute('home');

    $this->assertSoftDeleted($user);
});
