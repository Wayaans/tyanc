<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_requests', 'last_reassigned_at')) {
                $table->timestamp('last_reassigned_at')->nullable()->after('superseded_at')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable()->after('last_reassigned_at')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('last_reminded_at')->index();
            }
        });
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }
};
