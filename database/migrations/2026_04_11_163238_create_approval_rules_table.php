<?php

declare(strict_types=1);

use App\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('app_key', 64)->index();
            $table->string('resource_key', 64)->index();
            $table->string('action_key', 64)->index();
            $table->string('permission_name', 191)->unique();
            $table->boolean('enabled')->default(false)->index();
            $table->string('workflow_type', 32)->default(ApprovalRule::WorkflowSingle);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->unique(['app_key', 'resource_key', 'action_key'], 'approval_rules_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
