<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('locale', 12)->nullable();
            $table->string('timezone')->nullable();
            $table->string('appearance', 12)->nullable();
            $table->string('sidebar_variant', 32)->nullable();
            $table->string('spacing_density', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
