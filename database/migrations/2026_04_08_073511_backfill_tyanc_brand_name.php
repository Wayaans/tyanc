<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('settings.repositories.database.table', 'settings');

        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = [
            'app_name' => 'Tyanc',
            'company_legal_name' => 'Tyanc',
        ];

        foreach ($rows as $name => $value) {
            $currentPayload = DB::table($table)
                ->where('group', 'app')
                ->where('name', $name)
                ->value('payload');

            if (! is_string($currentPayload)) {
                continue;
            }

            if (in_array($currentPayload, ['"Laravel"', '""', 'null'], true)) {
                DB::table($table)
                    ->where('group', 'app')
                    ->where('name', $name)
                    ->update([
                        'payload' => json_encode($value, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-fix migration. Intentionally left empty.
    }
};
