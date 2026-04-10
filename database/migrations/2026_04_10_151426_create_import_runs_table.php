<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 80)->index();
            $table->string('status', 32)->default('pending_approval')->index();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->json('meta')->nullable();
            $table->text('failure_message')->nullable();
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
