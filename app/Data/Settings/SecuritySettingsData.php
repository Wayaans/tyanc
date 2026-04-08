<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Settings\SecuritySettings;
use Spatie\LaravelData\Data;

final class SecuritySettingsData extends Data
{
    public function __construct(
        public bool $enforce_2fa,
        public int $session_timeout,
    ) {}

    public static function fromSettings(SecuritySettings $settings): self
    {
        return new self(
            enforce_2fa: $settings->enforce_2fa,
            session_timeout: $settings->session_timeout,
        );
    }
}
