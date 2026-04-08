<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('backfills the stored app brand name from Laravel to Tyanc', function (): void {
    $table = (string) config('settings.repositories.database.table', 'settings');

    expect(Schema::hasTable($table))->toBeTrue();

    DB::table($table)->updateOrInsert(
        ['group' => 'app', 'name' => 'app_name'],
        ['payload' => json_encode('Laravel', JSON_THROW_ON_ERROR), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
    );

    DB::table($table)->updateOrInsert(
        ['group' => 'app', 'name' => 'company_legal_name'],
        ['payload' => json_encode('Laravel', JSON_THROW_ON_ERROR), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
    );

    $migration = require database_path('migrations/2026_04_08_073511_backfill_tyanc_brand_name.php');
    $migration->up();

    expect(DB::table($table)->where('group', 'app')->where('name', 'app_name')->value('payload'))
        ->toBe(json_encode('Tyanc', JSON_THROW_ON_ERROR))
        ->and(DB::table($table)->where('group', 'app')->where('name', 'company_legal_name')->value('payload'))
        ->toBe(json_encode('Tyanc', JSON_THROW_ON_ERROR));
});
