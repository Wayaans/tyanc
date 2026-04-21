<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    config([
        'fortify.features' => [
            Features::registration(),
            Features::emailVerification(),
        ],
    ]);
});

it('may verify email', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->get($verificationUrl);

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('redirects to dashboard if already verified', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->get($verificationUrl);

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('does not verify email while the feature is disabled', function (): void {
    config([
        'fortify.features' => [Features::registration()],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    $response->assertRedirectToRoute('verification.notice')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'warning'
            && $toast['message'] === 'Email verification is unavailable.');
});

it('requires valid signature', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $invalidUrl = route('verification.verify', [
        'id' => $user->getKey(),
        'hash' => sha1((string) $user->email),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->get($invalidUrl);

    $response->assertForbidden();
});
