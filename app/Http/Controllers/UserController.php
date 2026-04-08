<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUser;
use App\Actions\DeleteUser;
use App\Data\Auth\UserData;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\DeleteUserRequest;
use App\Models\User;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    public function create(): Response
    {
        return Inertia::render('user/Create', [
            'locales' => ['en'],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(CreateUserRequest $request, CreateUser $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $user = $action->handle(
            $validated,
            $request->string('password')->value(),
        );

        Auth::login($user);

        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json(UserData::fromModel($user), 201);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(DeleteUserRequest $request, #[CurrentUser] User $user, DeleteUser $action): RedirectResponse|JsonResponse
    {
        Auth::logout();

        $action->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return to_route('home');
    }
}
