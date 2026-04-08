<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class UserDefaultsSettings extends Settings
{
    public string $locale = 'en';

    public string $timezone = 'UTC';

    public string $appearance = 'system';

    public static function group(): string
    {
        return 'user_defaults';
    }
}
