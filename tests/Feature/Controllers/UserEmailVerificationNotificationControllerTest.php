<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

it('renders verify email page', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('verification.notice'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-email-verification-notification/Create')
            ->where('flash.toast', null));
});

it('redirects verified users to dashboard', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('verification.notice'));

    $response->assertRedirectToRoute('dashboard');
});

it('may send verification notification', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [
            Features::registration(),
            Features::emailVerification(),
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->post(route('verification.send'));

    $response->assertRedirectToRoute('verification.notice')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'success'
            && $toast['message'] === 'A new verification link has been sent to your email address.');

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.variant', 'success')
            ->where('flash.toast.message', 'A new verification link has been sent to your email address.'));
});

it('does not send verification notifications while the feature is disabled', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [Features::registration()],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->post(route('verification.send'));

    $response->assertRedirectToRoute('verification.notice')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'warning'
            && $toast['message'] === 'Email verification is unavailable.');

    Notification::assertNothingSent();
});

it('redirects verified users when sending notification', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [
            Features::registration(),
            Features::emailVerification(),
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('verification.notice')
        ->post(route('verification.send'));

    $response->assertRedirectToRoute('dashboard');

    Notification::assertNothingSent();
});
