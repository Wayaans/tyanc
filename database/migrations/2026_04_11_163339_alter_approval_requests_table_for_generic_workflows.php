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
            if (! Schema::hasColumn('approval_requests', 'rule_id')) {
                $table->foreignUuid('rule_id')->nullable()->after('id')->constrained('approval_rules')->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_requests', 'app_key')) {
                $table->string('app_key', 64)->nullable()->after('action')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'resource_key')) {
                $table->string('resource_key', 64)->nullable()->after('app_key')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'action_key')) {
                $table->string('action_key', 64)->nullable()->after('resource_key')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'cancelled_by_id')) {
                $table->foreignUuid('cancelled_by_id')->nullable()->after('reviewed_by_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_requests', 'previous_request_id')) {
                $table->foreignUuid('previous_request_id')->nullable()->after('cancelled_by_id')->constrained('approval_requests')->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_requests', 'superseded_by_id')) {
                $table->foreignUuid('superseded_by_id')->nullable()->after('previous_request_id')->constrained('approval_requests')->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_requests', 'subject_snapshot')) {
                $table->json('subject_snapshot')->nullable()->after('payload');
            }

            if (! Schema::hasColumn('approval_requests', 'before_payload')) {
                $table->json('before_payload')->nullable()->after('subject_snapshot');
            }

            if (! Schema::hasColumn('approval_requests', 'after_payload')) {
                $table->json('after_payload')->nullable()->after('before_payload');
            }

            if (! Schema::hasColumn('approval_requests', 'impact_summary')) {
                $table->text('impact_summary')->nullable()->after('after_payload');
            }

            if (! Schema::hasColumn('approval_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('reviewed_at')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('cancelled_at')->index();
            }

            if (! Schema::hasColumn('approval_requests', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('expires_at')->index();
            }
        });
    }

    public function down(): void
    {
        // Forward-fix migration for production upgrades.
    }
};
