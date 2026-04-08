<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Settings\UserDefaultsSettings;
use Spatie\LaravelData\Data;

final class UserDefaultsSettingsData extends Data
{
    public function __construct(
        public string $locale,
        public string $timezone,
        public string $appearance,
    ) {}

    public static function fromSettings(UserDefaultsSettings $settings): self
    {
        return new self(
            locale: $settings->locale,
            timezone: $settings->timezone,
            appearance: $settings->appearance,
        );
    }
}
