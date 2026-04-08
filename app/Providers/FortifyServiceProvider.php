<?php

declare(strict_types=1);

namespace App\Providers;

use DateTimeZone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootFortifyDefaults();
        $this->bootRateLimitingDefaults();
    }

    private function bootFortifyDefaults(): void
    {
        Fortify::loginView(fn () => Inertia::render('session/Create', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()) && Route::has('password.request'),
            'canRegister' => Features::enabled(Features::registration()) && Route::has('register'),
            'status' => session('status'),
        ]));
        Fortify::registerView(fn () => Inertia::render('user/Create', [
            'locales' => ['en'],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('user-email-reset-notification/Create', [
            'status' => session('status'),
            'enabled' => Features::enabled(Features::resetPasswords()),
        ]));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('user-password/Create', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'enabled' => Features::enabled(Features::resetPasswords()),
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('user-email-verification-notification/Create', [
            'status' => session('status'),
            'enabled' => Features::enabled(Features::emailVerification()),
        ]));
        Fortify::confirmPasswordView(fn () => Inertia::render('user-password-confirmation/Create', [
            'enabled' => Features::canManageTwoFactorAuthentication(),
        ]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('user-two-factor-authentication-challenge/Show', [
            'enabled' => Features::canManageTwoFactorAuthentication(),
        ]));
    }

    private function bootRateLimitingDefaults(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->value().$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
