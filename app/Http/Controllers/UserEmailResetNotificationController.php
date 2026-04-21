<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserEmailResetNotification;
use App\Http\Requests\CreateUserEmailResetNotificationRequest;
use App\Support\Notifications\FlashToast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final readonly class UserEmailResetNotificationController
{
    public function create(): Response
    {
        return Inertia::render('user-email-reset-notification/Create', [
            'enabled' => Features::enabled(Features::resetPasswords()),
        ]);
    }

    public function store(
        CreateUserEmailResetNotificationRequest $request,
        CreateUserEmailResetNotification $action
    ): RedirectResponse {
        if (! Features::enabled(Features::resetPasswords())) {
            return back()->with('toast', FlashToast::warning(__('Password reset is unavailable.'))->toArray());
        }

        $action->handle(['email' => $request->string('email')->value()]);

        return back()->with('toast', FlashToast::success(__('A reset link will be sent if the account exists.'))->toArray());
    }
}
