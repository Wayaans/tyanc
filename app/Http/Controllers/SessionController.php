<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Auth\UserData;
use App\Http\Requests\CreateSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final readonly class SessionController
{
    public function create(): Response
    {
        return Inertia::render('session/Create', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()) && Route::has('password.request'),
            'canRegister' => Features::enabled(Features::registration()) && Route::has('register'),
        ]);
    }

    public function store(CreateSessionRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->validateCredentials();

        if (Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json(UserData::fromModel($user));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
