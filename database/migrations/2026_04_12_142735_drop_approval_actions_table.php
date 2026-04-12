<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('approval_actions');
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_actions')) {
            return;
        }

        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_request_id')->unique()->constrained('approval_requests')->cascadeOnDelete();
            $table->string('handler');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
};
