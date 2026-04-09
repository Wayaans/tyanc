<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Tyanc\Settings\UpdateAppSettings;
use App\Data\Settings\AppSettingsData;
use App\Models\SettingsAsset;
use App\Models\User;
use App\Settings\AppSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AppSettingsController
{
    public function edit(Request $request, AppSettings $settings): Response|JsonResponse
    {
        Gate::authorize('manage-settings');

        $payload = [
            'settings' => AppSettingsData::fromSettings(
                $settings,
                SettingsAsset::resolveForKey(SettingsAsset::GLOBAL_BRANDING_KEY),
            ),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/Application', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateAppSettings $action): RedirectResponse|JsonResponse
    {
        $settings = $action->handle($user, [
            'app_name' => $request->string('app_name')->toString(),
            'company_legal_name' => $request->input('company_legal_name'),
            'app_logo' => $request->file('app_logo'),
            'favicon' => $request->file('favicon'),
            'login_cover_image' => $request->file('login_cover_image'),
            'remove_app_logo' => $request->boolean('remove_app_logo'),
            'remove_favicon' => $request->boolean('remove_favicon'),
            'remove_login_cover_image' => $request->boolean('remove_login_cover_image'),
        ]);

        $payload = [
            'settings' => AppSettingsData::fromSettings(
                $settings,
                SettingsAsset::resolveForKey(SettingsAsset::GLOBAL_BRANDING_KEY),
            ),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.application.edit');
    }
}
