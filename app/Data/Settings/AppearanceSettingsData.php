<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Settings\AppearanceSettings;
use Spatie\LaravelData\Data;

final class AppearanceSettingsData extends Data
{
    public function __construct(
        public string $primary_color,
        public string $secondary_color,
        public string $border_radius,
        public string $spacing_density,
        public float $spacing_density_value,
        public string $font_family,
        public string $font_family_stack,
        public string $sidebar_variant,
    ) {}

    public static function fromSettings(AppearanceSettings $settings): self
    {
        $spacingDensity = (array) config('tyanc.spacing_densities.'.$settings->spacing_density, config('tyanc.spacing_densities.default'));
        $fontFamily = (array) config('tyanc.font_families.'.$settings->font_family, config('tyanc.font_families.geist'));

        return new self(
            primary_color: $settings->primary_color,
            secondary_color: $settings->secondary_color,
            border_radius: $settings->border_radius,
            spacing_density: $settings->spacing_density,
            spacing_density_value: (float) ($spacingDensity['value'] ?? 1.0),
            font_family: $settings->font_family,
            font_family_stack: (string) ($fontFamily['stack'] ?? config('tyanc.font_families.geist.stack')),
            sidebar_variant: $settings->sidebar_variant,
        );
    }
}
