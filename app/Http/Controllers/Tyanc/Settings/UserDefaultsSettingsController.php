<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Settings\UpdateUserDefaultsSettings;
use App\Data\Settings\UserDefaultsSettingsData;
use App\Models\User;
use App\Settings\UserDefaultsSettings;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserDefaultsSettingsController
{
    public function edit(Request $request, UserDefaultsSettings $settings): Response|JsonResponse
    {
        Gate::authorize('manage-settings');

        $payload = [
            'settings' => UserDefaultsSettingsData::fromSettings($settings),
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'locales' => $this->locales(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/UserDefaults', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateUserDefaultsSettings $action): RedirectResponse|JsonResponse
    {
        $settings = $action->handle($user, [
            'locale' => $request->string('locale')->toString(),
            'timezone' => $request->string('timezone')->toString(),
            'appearance' => $request->input('appearance'),
        ]);

        $payload = [
            'settings' => UserDefaultsSettingsData::fromSettings($settings),
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'locales' => $this->locales(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.user-defaults.edit');
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return Collection::make(array_keys((array) config('tyanc.supported_locales', [])))
            ->push((string) config('app.locale', 'en'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    private function mapSimpleOptions(array $options): array
    {
        return Collection::make($options)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
