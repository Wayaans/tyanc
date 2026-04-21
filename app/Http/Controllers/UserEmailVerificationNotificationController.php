<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserEmailVerificationNotification;
use App\Models\User;
use App\Support\Notifications\FlashToast;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final readonly class UserEmailVerificationNotificationController
{
    public function create(Request $request, #[CurrentUser] User $user): Response|RedirectResponse
    {
        return $user->hasVerifiedEmail()
            ? redirect()->intended(route('dashboard', absolute: false))
            : Inertia::render('user-email-verification-notification/Create', [
                'enabled' => Features::enabled(Features::emailVerification()),
            ]);
    }

    public function store(#[CurrentUser] User $user, CreateUserEmailVerificationNotification $action): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if (! Features::enabled(Features::emailVerification())) {
            return back()->with('toast', FlashToast::warning(__('Email verification is unavailable.'))->toArray());
        }

        $action->handle($user);

        return back()->with('toast', FlashToast::success(
            __('A new verification link has been sent to your email address.'),
        )->toArray());
    }
}
