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
        if (! Schema::hasTable('approval_requests')) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('approval_requests', 'mode')) {
                $table->string('mode', 32)
                    ->default(ApprovalMode::Grant->value)
                    ->after('action_key')
                    ->index();
            }

            if (! Schema::hasColumn('approval_requests', 'subject_revision')) {
                $table->string('subject_revision', 191)
                    ->nullable()
                    ->after('subject_id')
                    ->index();
            }
        });

        DB::table('approval_requests')
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
