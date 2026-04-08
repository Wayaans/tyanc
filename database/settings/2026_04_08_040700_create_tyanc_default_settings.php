<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! Schema::hasTable(config('settings.repositories.database.table', 'settings'))) {
            return;
        }

        $this->migrator->add('app.app_name', (string) config('app.name', 'Tyanc'));
        $this->migrator->add('app.company_legal_name', (string) config('app.name', 'Tyanc'));
        $this->migrator->add('app.app_logo');
        $this->migrator->add('app.favicon');
        $this->migrator->add('app.login_cover_image');

        $this->migrator->add('appearance.primary_color', (string) config('tyanc.theme.primary_color'));
        $this->migrator->add('appearance.secondary_color', (string) config('tyanc.theme.secondary_color'));
        $this->migrator->add('appearance.border_radius', (string) config('tyanc.theme.radius'));
        $this->migrator->add('appearance.spacing_density', (string) config('tyanc.theme.spacing_density'));
        $this->migrator->add('appearance.font_family', (string) config('tyanc.theme.font_family'));
        $this->migrator->add('appearance.sidebar_variant', (string) config('tyanc.theme.sidebar_variant'));

        $this->migrator->add('security.enforce_2fa', false);
        $this->migrator->add('security.session_timeout', (int) config('session.lifetime', 120));

        $this->migrator->add('user_defaults.locale', (string) config('app.locale', 'en'));
        $this->migrator->add('user_defaults.timezone', (string) config('app.timezone', 'UTC'));
        $this->migrator->add('user_defaults.appearance', (string) config('tyanc.theme.appearance', 'system'));
    }

    public function down(): void
    {
        if (! Schema::hasTable(config('settings.repositories.database.table', 'settings'))) {
            return;
        }

        $this->migrator->delete('app.app_name');
        $this->migrator->delete('app.company_legal_name');
        $this->migrator->delete('app.app_logo');
        $this->migrator->delete('app.favicon');
        $this->migrator->delete('app.login_cover_image');

        $this->migrator->delete('appearance.primary_color');
        $this->migrator->delete('appearance.secondary_color');
        $this->migrator->delete('appearance.border_radius');
        $this->migrator->delete('appearance.spacing_density');
        $this->migrator->delete('appearance.font_family');
        $this->migrator->delete('appearance.sidebar_variant');

        $this->migrator->delete('security.enforce_2fa');
        $this->migrator->delete('security.session_timeout');

        $this->migrator->delete('user_defaults.locale');
        $this->migrator->delete('user_defaults.timezone');
        $this->migrator->delete('user_defaults.appearance');
    }
};
