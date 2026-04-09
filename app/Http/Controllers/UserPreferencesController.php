<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Settings\ResolveRuntimeSettings;
use App\Actions\Settings\UpdateUserPreferences;
use App\Models\User;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserPreferencesController
{
    public function edit(Request $request, #[CurrentUser] User $user, ResolveRuntimeSettings $resolver): Response|JsonResponse
    {
        $runtimeSettings = $resolver->handle($user, $request);
        $payload = $this->payload($runtimeSettings);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Preferences', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateUserPreferences $action, ResolveRuntimeSettings $resolver): RedirectResponse|JsonResponse
    {
        $action->handle($user, [
            'locale' => $request->input('locale'),
            'timezone' => $request->input('timezone'),
            'appearance' => $request->input('appearance'),
            'sidebar_variant' => $request->input('sidebar_variant'),
            'spacing_density' => $request->input('spacing_density'),
        ]);

        $payload = $this->payload($resolver->handle($user->fresh(), $request));

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('settings.preferences.edit');
    }

    /**
     * @param  array<string, mixed>  $runtimeSettings
     * @return array<string, mixed>
     */
    private function payload(array $runtimeSettings): array
    {
        return [
            'preferences' => $runtimeSettings['preferences'],
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'locales' => array_values(array_keys((array) config('tyanc.supported_locales', []))),
            'timezones' => DateTimeZone::listIdentifiers(),
            'sidebarVariants' => $this->mapSimpleOptions((array) config('tyanc.sidebar_variants', [])),
            'spacingDensities' => $this->spacingDensities(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, density: float}>
     */
    private function spacingDensities(): array
    {
        return Collection::make((array) config('tyanc.spacing_densities', []))
            ->map(fn (array $density, string $value): array => [
                'value' => $value,
                'label' => __((string) ($density['label'] ?? $value)),
                'density' => (float) ($density['value'] ?? 1.0),
            ])
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
                'label' => __($label),
            ])
            ->values()
            ->all();
    }
}
