<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class NotificationSettings extends Settings
{
    public bool $sonner_enabled = true;

    public bool $email_enabled = true;

    public bool $reverb_enabled = true;

    public static function group(): string
    {
        return 'notifications';
    }
}
