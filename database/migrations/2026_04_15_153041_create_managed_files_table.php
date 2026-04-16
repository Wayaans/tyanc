<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_files', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('source', 32)->default('public_disk');
            $table->string('app_key', 80)->default('unassigned')->index();
            $table->string('resource_key', 120)->default('files');
            $table->string('folder_path', 255)->default('unassigned/root')->index();
            $table->string('relative_path', 512);
            $table->string('directory_path', 255)->default('');
            $table->string('name');
            $table->string('file_name');
            $table->string('extension', 40)->nullable();
            $table->string('mime_type')->default('application/octet-stream');
            $table->string('mime_group', 40)->default('application')->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('collection_name')->nullable();
            $table->unsignedBigInteger('media_id')->nullable()->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->string('uploaded_by_id')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->json('custom_properties')->nullable();
            $table->boolean('is_deletable')->default(false);
            $table->timestamp('uploaded_at')->nullable()->index();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['disk', 'relative_path']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_files');
    }
};
