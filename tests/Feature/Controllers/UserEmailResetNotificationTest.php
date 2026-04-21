<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

it('renders forgot password page', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('password.request'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-email-reset-notification/Create')
            ->where('flash.toast', null));
});

it('may send password reset notification', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [
            Features::registration(),
            Features::resetPasswords(),
        ],
    ]);

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'success'
            && $toast['message'] === 'A reset link will be sent if the account exists.');

    Notification::assertSentTo($user, ResetPassword::class);

    $this->get(route('password.request'))
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.variant', 'success')
            ->where('flash.toast.message', 'A reset link will be sent if the account exists.'));
});

it('returns generic message for non-existent email', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [
            Features::registration(),
            Features::resetPasswords(),
        ],
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'success'
            && $toast['message'] === 'A reset link will be sent if the account exists.');

    Notification::assertNothingSent();
});

it('requires email', function (): void {
    config([
        'fortify.features' => [
            Features::registration(),
            Features::resetPasswords(),
        ],
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), []);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHasErrors('email');
});

it('requires valid email format', function (): void {
    config([
        'fortify.features' => [
            Features::registration(),
            Features::resetPasswords(),
        ],
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'not-an-email',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHasErrors('email');
});

it('does not send password reset notifications while the feature is disabled', function (): void {
    Notification::fake();

    config([
        'fortify.features' => [Features::registration()],
    ]);

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => $user->email,
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'warning'
            && $toast['message'] === 'Password reset is unavailable.');

    Notification::assertNothingSent();
});

it('redirects authenticated users away from forgot password', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('password.request'));

    $response->assertRedirectToRoute('dashboard');
});
