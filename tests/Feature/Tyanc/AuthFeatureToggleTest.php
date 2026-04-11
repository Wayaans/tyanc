<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Features;

it('only enables registration by default', function (): void {
    expect(config('fortify.features'))
        ->toContain(Features::registration())
        ->not->toContain(Features::resetPasswords())
        ->not->toContain(Features::emailVerification())
        ->not->toContain(Features::twoFactorAuthentication());
});

it('hides disabled auth flows on the login page', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->component('session/Create')
            ->where('canResetPassword', false)
            ->where('canRegister', true));
});

it('marks email verification as disabled on the account page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.account.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Account')
            ->where('mustVerifyEmail', false));
});

it('shows password reset as disabled on the forgot-password page', function (): void {
    $this->get(route('password.request'))
        ->assertInertia(fn ($page) => $page
            ->component('user-email-reset-notification/Create')
            ->where('enabled', false));
});

it('shows password reset as disabled on the reset-password page', function (): void {
    $this->get(route('password.reset', ['token' => 'phase-two-token']))
        ->assertInertia(fn ($page) => $page
            ->component('user-password/Create')
            ->where('enabled', false));
});

it('shows email verification as disabled on the verification notice page', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertInertia(fn ($page) => $page
            ->component('user-email-verification-notification/Create')
            ->where('enabled', false));
});

it('shows password confirmation as disabled by default', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertInertia(fn ($page) => $page
            ->component('user-password-confirmation/Create')
            ->where('enabled', false));
});

it('marks two factor management as disabled by default', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $this->get(route('two-factor.show'))
        ->assertInertia(fn ($page) => $page
            ->component('user-two-factor-authentication/Show')
            ->where('canManageTwoFactor', false));
});
