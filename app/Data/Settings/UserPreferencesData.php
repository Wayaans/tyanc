<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Models\UserPreference;
use Spatie\LaravelData\Data;

final class UserPreferencesData extends Data
{
    public function __construct(
        public ?string $locale,
        public ?string $timezone,
        public ?string $appearance,
        public ?string $sidebar_variant,
        public ?string $spacing_density,
        public string $resolved_locale,
        public string $resolved_timezone,
        public string $resolved_appearance,
        public string $resolved_sidebar_variant,
        public string $resolved_spacing_density,
        public float $resolved_spacing_density_value,
    ) {}

    public static function fromState(
        ?UserPreference $preferences,
        string $resolvedLocale,
        string $resolvedTimezone,
        string $resolvedAppearance,
        string $resolvedSidebarVariant,
        string $resolvedSpacingDensity,
        float $resolvedSpacingDensityValue,
    ): self {
        return new self(
            locale: $preferences?->locale,
            timezone: $preferences?->timezone,
            appearance: $preferences?->appearance,
            sidebar_variant: $preferences?->sidebar_variant,
            spacing_density: $preferences?->spacing_density,
            resolved_locale: $resolvedLocale,
            resolved_timezone: $resolvedTimezone,
            resolved_appearance: $resolvedAppearance,
            resolved_sidebar_variant: $resolvedSidebarVariant,
            resolved_spacing_density: $resolvedSpacingDensity,
            resolved_spacing_density_value: $resolvedSpacingDensityValue,
        );
    }
}
