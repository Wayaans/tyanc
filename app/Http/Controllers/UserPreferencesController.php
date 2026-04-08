<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Settings\ResolveRuntimeSettings;
use App\Actions\Settings\UpdateUserPreferences;
use App\Models\User;
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

        $payload = [
            'preferences' => $runtimeSettings['preferences'],
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'sidebarVariants' => $this->mapSimpleOptions((array) config('tyanc.sidebar_variants', [])),
            'spacingDensities' => $this->spacingDensities(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Preferences', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateUserPreferences $action, ResolveRuntimeSettings $resolver): RedirectResponse|JsonResponse
    {
        $action->handle($user, [
            'appearance' => $request->input('appearance'),
            'sidebar_variant' => $request->input('sidebar_variant'),
            'spacing_density' => $request->input('spacing_density'),
        ]);

        $runtimeSettings = $resolver->handle($user->fresh(), $request);

        $payload = [
            'preferences' => $runtimeSettings['preferences'],
            'appearances' => $this->mapSimpleOptions((array) config('tyanc.appearance_options', [])),
            'sidebarVariants' => $this->mapSimpleOptions((array) config('tyanc.sidebar_variants', [])),
            'spacingDensities' => $this->spacingDensities(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('settings.preferences.edit');
    }

    /**
     * @return list<array{value: string, label: string, density: float}>
     */
    private function spacingDensities(): array
    {
        return Collection::make((array) config('tyanc.spacing_densities', []))
            ->map(fn (array $density, string $value): array => [
                'value' => $value,
                'label' => (string) ($density['label'] ?? $value),
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
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
