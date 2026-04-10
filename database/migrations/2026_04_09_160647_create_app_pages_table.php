<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('key', 120);
            $table->string('label', 160);
            $table->string('route_name')->nullable();
            $table->string('path')->nullable();
            $table->string('permission_name')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('enabled')->default(true)->index();
            $table->boolean('is_navigation')->default(true)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();

            $table->unique(['app_id', 'key']);
            $table->unique(['app_id', 'route_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_pages');
    }
};
