<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->string('route_prefix', 120)->unique();
            $table->string('icon', 80)->default('layout-grid');
            $table->string('permission_namespace', 64)->unique();
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
