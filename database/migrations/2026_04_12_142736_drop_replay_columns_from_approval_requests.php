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
            if (Schema::hasColumn('approval_requests', 'previous_request_id')) {
                $table->dropForeign(['previous_request_id']);
            }

            if (Schema::hasColumn('approval_requests', 'superseded_by_id')) {
                $table->dropForeign(['superseded_by_id']);
            }
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $columns = collect([
                'previous_request_id',
                'superseded_by_id',
                'before_payload',
                'after_payload',
                'impact_summary',
            ])
                ->filter(fn (string $column): bool => Schema::hasColumn('approval_requests', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_requests', 'previous_request_id')) {
                $table->foreignUuid('previous_request_id')
                    ->nullable()
                    ->after('consumed_by_id')
                    ->constrained('approval_requests')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_requests', 'superseded_by_id')) {
                $table->foreignUuid('superseded_by_id')
                    ->nullable()
                    ->after('previous_request_id')
                    ->constrained('approval_requests')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('approval_requests', 'before_payload')) {
                $table->json('before_payload')
                    ->nullable()
                    ->after('subject_snapshot');
            }

            if (! Schema::hasColumn('approval_requests', 'after_payload')) {
                $table->json('after_payload')
                    ->nullable()
                    ->after('before_payload');
            }

            if (! Schema::hasColumn('approval_requests', 'impact_summary')) {
                $table->text('impact_summary')
                    ->nullable()
                    ->after('after_payload');
            }
        });
    }
};
