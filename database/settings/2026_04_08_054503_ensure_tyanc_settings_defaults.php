<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $table = (string) config('settings.repositories.database.table', 'settings');

        if (! Schema::hasTable($table)) {
            return;
        }

        $settings = [
            'app' => [
                'app_name' => (string) config('app.name', 'Tyanc'),
                'company_legal_name' => (string) config('app.name', 'Tyanc'),
                'app_logo' => null,
                'favicon' => null,
                'login_cover_image' => null,
            ],
            'appearance' => [
                'primary_color' => (string) config('tyanc.theme.primary_color'),
                'secondary_color' => (string) config('tyanc.theme.secondary_color'),
                'border_radius' => (string) config('tyanc.theme.radius'),
                'spacing_density' => (string) config('tyanc.theme.spacing_density'),
                'font_family' => (string) config('tyanc.theme.font_family'),
                'sidebar_variant' => (string) config('tyanc.theme.sidebar_variant'),
            ],
            'security' => [
                'enforce_2fa' => false,
                'session_timeout' => (int) config('session.lifetime', 120),
            ],
            'user_defaults' => [
                'locale' => (string) config('app.locale', 'en'),
                'timezone' => (string) config('app.timezone', 'UTC'),
                'appearance' => (string) config('tyanc.theme.appearance', 'system'),
            ],
        ];

        foreach ($settings as $group => $values) {
            foreach ($values as $name => $value) {
                $exists = DB::table($table)
                    ->where('group', $group)
                    ->where('name', $name)
                    ->exists();

                if (! $exists) {
                    $this->migrator->add(sprintf('%s.%s', $group, $name), $value);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-fix migration. Intentionally left empty.
    }
};
