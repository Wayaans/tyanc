<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserEmailResetNotification;
use App\Http\Requests\CreateUserEmailResetNotificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final readonly class UserEmailResetNotificationController
{
    public function create(Request $request): Response
    {
        return Inertia::render('user-email-reset-notification/Create', [
            'status' => $request->session()->get('status'),
            'enabled' => Features::enabled(Features::resetPasswords()),
        ]);
    }

    public function store(
        CreateUserEmailResetNotificationRequest $request,
        CreateUserEmailResetNotification $action
    ): RedirectResponse {
        $action->handle(['email' => $request->string('email')->value()]);

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
