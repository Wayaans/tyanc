<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_rules')) {
            return;
        }

        Schema::table('approval_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_rules', 'reminder_after_minutes')) {
                $table->unsignedInteger('reminder_after_minutes')->nullable()->after('conditions');
            }

            if (! Schema::hasColumn('approval_rules', 'escalation_after_minutes')) {
                $table->unsignedInteger('escalation_after_minutes')->nullable()->after('reminder_after_minutes');
            }
        });
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }
};
