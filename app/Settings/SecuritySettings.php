<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class SecuritySettings extends Settings
{
    public bool $enforce_2fa = false;

    public int $session_timeout = 120;

    public static function group(): string
    {
        return 'security';
    }
}
