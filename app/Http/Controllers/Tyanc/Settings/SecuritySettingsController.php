<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Settings\UpdateSecuritySettings;
use App\Data\Settings\SecuritySettingsData;
use App\Models\User;
use App\Settings\SecuritySettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SecuritySettingsController
{
    public function edit(Request $request, SecuritySettings $settings): Response|JsonResponse
    {
        Gate::authorize('manage-settings');

        $payload = [
            'settings' => SecuritySettingsData::fromSettings($settings),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/Security', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateSecuritySettings $action): RedirectResponse|JsonResponse
    {
        $settings = $action->handle($user, [
            'enforce_2fa' => $request->boolean('enforce_2fa'),
            'session_timeout' => $request->integer('session_timeout'),
        ]);

        $payload = [
            'settings' => SecuritySettingsData::fromSettings($settings),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.security.edit');
    }
}
