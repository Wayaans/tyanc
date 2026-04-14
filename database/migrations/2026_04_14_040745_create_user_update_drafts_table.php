<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_update_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('committed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1)->index();
            $table->text('payload')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamp('committed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_by_id', 'committed_at'], 'user_update_drafts_active_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_update_drafts');
    }
};
