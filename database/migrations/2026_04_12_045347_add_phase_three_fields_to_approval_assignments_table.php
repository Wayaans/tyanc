<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_assignments')) {
            return;
        }

        Schema::table('approval_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_assignments', 'step_order_snapshot')) {
                $table->unsignedInteger('step_order_snapshot')->nullable()->after('approval_rule_step_id');
            }

            if (! Schema::hasColumn('approval_assignments', 'step_label_snapshot')) {
                $table->string('step_label_snapshot')->nullable()->after('step_order_snapshot');
            }

            if (! Schema::hasColumn('approval_assignments', 'role_name_snapshot')) {
                $table->string('role_name_snapshot')->nullable()->after('step_label_snapshot');
            }
        });
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }
};
