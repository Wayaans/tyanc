<?php

declare(strict_types=1);

use App\Enums\ApprovalMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_rules')) {
            return;
        }

        Schema::table('approval_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_rules', 'mode')) {
                $table->string('mode', 32)
                    ->default(ApprovalMode::Grant->value)
                    ->after('enabled')
                    ->index();
            }

            if (! Schema::hasColumn('approval_rules', 'managed_by_config')) {
                $table->boolean('managed_by_config')
                    ->default(false)
                    ->after('mode')
                    ->index();
            }

            if (! Schema::hasColumn('approval_rules', 'source_key')) {
                $table->string('source_key', 191)
                    ->nullable()
                    ->after('managed_by_config')
                    ->index();
            }

            if (! Schema::hasColumn('approval_rules', 'config_hash')) {
                $table->string('config_hash', 64)
                    ->nullable()
                    ->after('source_key');
            }

            if (! Schema::hasColumn('approval_rules', 'retired_at')) {
                $table->timestamp('retired_at')
                    ->nullable()
                    ->after('config_hash')
                    ->index();
            }

            if (! Schema::hasColumn('approval_rules', 'retired_reason')) {
                $table->string('retired_reason', 255)
                    ->nullable()
                    ->after('retired_at');
            }
        });

        DB::table('approval_rules')
            ->whereNull('mode')
            ->update([
                'mode' => ApprovalMode::Grant->value,
            ]);
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }
};
