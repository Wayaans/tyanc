<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Settings;

use App\Models\User;
use App\Settings\AppearanceSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class UpdateAppearanceSettings
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): AppearanceSettings
    {
        Gate::forUser($user)->authorize('manage-settings');

        $validated = Validator::make($attributes, [
            'primary_color' => ['required', 'string', 'max:40', 'regex:/^(#[0-9A-Fa-f]{3,8}|oklch\([^)]+\))$/'],
            'secondary_color' => ['required', 'string', 'max:40', 'regex:/^(#[0-9A-Fa-f]{3,8}|oklch\([^)]+\))$/'],
            'border_radius' => ['required', 'string', 'max:20', 'regex:/^\d+(?:\.\d+)?(?:px|rem)$/'],
            'spacing_density' => ['required', Rule::in(array_keys((array) config('tyanc.spacing_densities', [])))],
            'font_family' => ['required', Rule::in(array_keys((array) config('tyanc.font_families', [])))],
            'sidebar_variant' => ['required', Rule::in(array_keys((array) config('tyanc.sidebar_variants', [])))],
        ])->validate();

        $settings = resolve(AppearanceSettings::class);
        $settings->primary_color = (string) $validated['primary_color'];
        $settings->secondary_color = (string) $validated['secondary_color'];
        $settings->border_radius = (string) $validated['border_radius'];
        $settings->spacing_density = (string) $validated['spacing_density'];
        $settings->font_family = (string) $validated['font_family'];
        $settings->sidebar_variant = (string) $validated['sidebar_variant'];
        $settings->save();

        return $settings;
    }
}
