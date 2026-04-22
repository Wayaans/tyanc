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

        $this->migrator->add('notifications.sonner_enabled', true);
        $this->migrator->add('notifications.email_enabled', true);
        $this->migrator->add('notifications.reverb_enabled', true);
    }

    public function down(): void
    {
        if (! Schema::hasTable(config('settings.repositories.database.table', 'settings'))) {
            return;
        }

        $this->migrator->delete('notifications.sonner_enabled');
        $this->migrator->delete('notifications.email_enabled');
        $this->migrator->delete('notifications.reverb_enabled');
    }
};
