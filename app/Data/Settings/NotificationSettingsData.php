<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Settings\NotificationSettings;
use Spatie\LaravelData\Data;

final class NotificationSettingsData extends Data
{
    public function __construct(
        public bool $sonner_enabled,
        public bool $email_enabled,
        public bool $reverb_enabled,
    ) {}

    public static function fromSettings(NotificationSettings $settings): self
    {
        return new self(
            sonner_enabled: $settings->sonner_enabled,
            email_enabled: $settings->email_enabled,
            reverb_enabled: $settings->reverb_enabled,
        );
    }
}
