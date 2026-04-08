<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class AppearanceSettings extends Settings
{
    public string $primary_color = 'oklch(0.5 0.17 200)';

    public string $secondary_color = 'oklch(0.96 0 0)';

    public string $border_radius = '0.625rem';

    public string $spacing_density = 'default';

    public string $font_family = 'geist';

    public string $sidebar_variant = 'inset';

    public static function group(): string
    {
        return 'appearance';
    }
}
