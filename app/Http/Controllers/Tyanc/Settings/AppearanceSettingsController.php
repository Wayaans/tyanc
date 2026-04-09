<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc\Settings;

use App\Actions\Settings\UpdateAppearanceSettings;
use App\Data\Settings\AppearanceSettingsData;
use App\Models\User;
use App\Settings\AppearanceSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AppearanceSettingsController
{
    public function edit(Request $request, AppearanceSettings $settings): Response|JsonResponse
    {
        Gate::authorize('manage-settings');

        $payload = [
            'settings' => AppearanceSettingsData::fromSettings($settings),
            'fontFamilies' => $this->fontFamilies(),
            'sidebarVariants' => $this->sidebarVariants(),
            'spacingDensities' => $this->spacingDensities(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/settings/Appearance', $payload);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateAppearanceSettings $action): RedirectResponse|JsonResponse
    {
        $settings = $action->handle($user, [
            'primary_color' => $request->string('primary_color')->toString(),
            'secondary_color' => $request->string('secondary_color')->toString(),
            'border_radius' => $request->string('border_radius')->toString(),
            'spacing_density' => $request->input('spacing_density'),
            'font_family' => $request->input('font_family'),
            'sidebar_variant' => $request->input('sidebar_variant'),
        ]);

        $payload = [
            'settings' => AppearanceSettingsData::fromSettings($settings),
            'fontFamilies' => $this->fontFamilies(),
            'sidebarVariants' => $this->sidebarVariants(),
            'spacingDensities' => $this->spacingDensities(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return to_route('tyanc.settings.appearance.edit');
    }

    /**
     * @return list<array{value: string, label: string, stack: string}>
     */
    private function fontFamilies(): array
    {
        return collect((array) config('tyanc.font_families', []))
            ->map(fn (array $font, string $value): array => [
                'value' => $value,
                'label' => __((string) ($font['label'] ?? $value)),
                'stack' => (string) ($font['stack'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sidebarVariants(): array
    {
        return $this->mapSimpleOptions((array) config('tyanc.sidebar_variants', []));
    }

    /**
     * @return list<array{value: string, label: string, density: float}>
     */
    private function spacingDensities(): array
    {
        return collect((array) config('tyanc.spacing_densities', []))
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
