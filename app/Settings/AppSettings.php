<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class AppSettings extends Settings
{
    public string $app_name = 'Tyanc';

    public ?string $company_legal_name = 'Tyanc';

    public ?string $app_logo = null;

    public ?string $favicon = null;

    public ?string $login_cover_image = null;

    public static function group(): string
    {
        return 'app';
    }
}
