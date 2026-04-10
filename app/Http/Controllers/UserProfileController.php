<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUser;
use App\Data\Auth\UserData;
use App\Enums\UserStatus;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final readonly class UserProfileController
{
    public function edit(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        $payload = [
            'status' => $request->session()->get('status'),
            'mustVerifyEmail' => Features::enabled(Features::emailVerification()) && $user instanceof MustVerifyEmail,
            'canManageStatus' => $this->canManageStatus($user),
            'locales' => array_keys((array) config('tyanc.supported_locales', [])),
            'statuses' => UserStatus::values(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                ...$payload,
                'user' => UserData::fromModel($user->loadMissing('profile')),
            ]);
        }

        return Inertia::render('user-profile/Edit', $payload);
    }

    public function update(UpdateUserProfileRequest $request, #[CurrentUser] User $user, UpdateUser $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        if (! $this->canManageStatus($user)) {
            unset($validated['status']);
        }

        $updatedUser = $action->handle($user, $validated);

        if ($request->wantsJson()) {
            return response()->json(UserData::fromModel($updatedUser));
        }

        return to_route('user-profile.edit');
    }

    private function canManageStatus(User $user): bool
    {
        if ($user->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return true;
        }

        $permissionName = PermissionKey::tyanc('users', 'manage');

        return Permission::query()->where('name', $permissionName)->where('guard_name', 'web')->exists()
            && $user->hasPermissionTo($permissionName);
    }
}
