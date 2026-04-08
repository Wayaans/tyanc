<?php

declare(strict_types=1);

use App\Actions\UpdateUser;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

it('may update a user and profile', function (): void {
    $user = User::factory()->create([
        'username' => 'old-name',
        'email' => 'old@email.com',
    ]);

    $action = resolve(UpdateUser::class);

    $updatedUser = $action->handle($user, [
        'name' => 'New Name',
        'username' => 'new-name',
        'city' => 'Denpasar',
    ]);

    expect($updatedUser->refresh()->username)->toBe('new-name')
        ->and($updatedUser->name)->toBe('New Name')
        ->and($updatedUser->profile)->not->toBeNull()
        ->and($updatedUser->profile->city)->toBe('Denpasar');
});

it('keeps email verified and does not send a notification when email changes while verification is disabled', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'old@email.com',
        'email_verified_at' => now(),
    ]);

    $action = resolve(UpdateUser::class);

    $updatedUser = $action->handle($user, [
        'email' => 'new@email.com',
    ]);

    expect($updatedUser->refresh()->email)->toBe('new@email.com')
        ->and($updatedUser->email_verified_at)->not->toBeNull();

    Notification::assertNothingSent();
});

it('resets email verification and sends notification when the feature is enabled', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [
            Features::registration(),
            Features::emailVerification(),
        ],
    ]);

    $user = User::factory()->create([
        'email' => 'old@email.com',
        'email_verified_at' => now(),
    ]);

    $action = resolve(UpdateUser::class);

    $updatedUser = $action->handle($user, [
        'email' => 'new@email.com',
    ]);

    expect($updatedUser->refresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($updatedUser, VerifyEmail::class);
});
