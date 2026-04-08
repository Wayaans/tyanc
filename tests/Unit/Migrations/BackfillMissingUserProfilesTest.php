<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates missing user profiles for legacy users', function (): void {
    Schema::dropIfExists('user_profiles');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('username')->unique();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('user_profiles', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('phone_number')->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('gender')->nullable();
        $table->string('address_line_1')->nullable();
        $table->string('address_line_2')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('country', 2)->nullable();
        $table->string('postal_code')->nullable();
        $table->string('company_name')->nullable();
        $table->string('job_title')->nullable();
        $table->text('bio')->nullable();
        $table->json('social_links')->nullable();
        $table->timestamps();
    });

    DB::table('users')->insert([
        'id' => '00000000-0000-0000-0000-000000000001',
        'username' => 'ary-app',
        'name' => 'Ary App',
        'email' => 'ary@app.com',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_04_08_030100_backfill_missing_user_profiles.php');
    $migration->up();

    $profile = DB::table('user_profiles')->where('user_id', '00000000-0000-0000-0000-000000000001')->first();

    expect($profile)->not->toBeNull()
        ->and($profile->first_name)->toBe('Ary')
        ->and($profile->last_name)->toBe('App');
});
