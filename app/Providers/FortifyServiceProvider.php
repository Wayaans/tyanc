<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Responses\PasswordConfirmedResponse;
use App\Http\Responses\TwoFactorConfirmedResponse;
use App\Http\Responses\TwoFactorDisabledResponse;
use App\Http\Responses\TwoFactorEnabledResponse;
use DateTimeZone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;
use Laravel\Fortify\Contracts\TwoFactorConfirmedResponse as TwoFactorConfirmedResponseContract;
use Laravel\Fortify\Contracts\TwoFactorDisabledResponse as TwoFactorDisabledResponseContract;
use Laravel\Fortify\Contracts\TwoFactorEnabledResponse as TwoFactorEnabledResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PasswordConfirmedResponseContract::class, PasswordConfirmedResponse::class);
        $this->app->singleton(TwoFactorConfirmedResponseContract::class, TwoFactorConfirmedResponse::class);
        $this->app->singleton(TwoFactorDisabledResponseContract::class, TwoFactorDisabledResponse::class);
        $this->app->singleton(TwoFactorEnabledResponseContract::class, TwoFactorEnabledResponse::class);
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
        ]));
        Fortify::registerView(fn () => Inertia::render('user/Create', [
            'locales' => array_keys((array) config('tyanc.supported_locales', [])),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('user-email-reset-notification/Create', [
            'enabled' => Features::enabled(Features::resetPasswords()),
        ]));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('user-password/Create', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'enabled' => Features::enabled(Features::resetPasswords()),
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('user-email-verification-notification/Create', [
            'enabled' => Features::enabled(Features::emailVerification()),
        ]));
        Fortify::confirmPasswordView(fn () => Inertia::render('user-password-confirmation/Create', [
            'enabled' => true,
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
