<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignUuid('approval_rule_step_id')->nullable()->constrained('approval_rule_steps')->nullOnDelete();
            $table->foreignUuid('assigned_to_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignUuid('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['approval_request_id', 'approval_rule_step_id', 'assigned_to_id'], 'approval_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_assignments');
    }
};
