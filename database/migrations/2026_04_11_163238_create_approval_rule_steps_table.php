<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rule_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_rule_id')->constrained('approval_rules')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->unsignedInteger('step_order')->default(1);
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['approval_rule_id', 'step_order'], 'approval_rule_steps_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rule_steps');
    }
};
