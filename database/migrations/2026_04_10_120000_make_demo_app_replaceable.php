<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('apps')) {
            return;
        }

        DB::table('apps')
            ->where('key', 'demo')
            ->update([
                'is_system' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('apps')) {
            return;
        }

        DB::table('apps')
            ->where('key', 'demo')
            ->update([
                'is_system' => true,
                'updated_at' => now(),
            ]);
    }
};
