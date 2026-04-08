<?php

declare(strict_types=1);

use App\Actions\CreateUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds the missing phase two columns to a legacy users table and backfills usernames', function (): void {
    Schema::dropIfExists('user_profiles');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    DB::table('users')->insert([
        'id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Ary App',
        'email' => 'ary@app.com',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_04_08_023116_sync_existing_users_table_with_phase_two_schema.php');
    $migration->up();

    expect(Schema::hasColumn('users', 'deleted_at'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'username'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'timezone'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'locale'))->toBeTrue()
        ->and(Schema::hasIndex('users', ['username'], 'unique'))->toBeTrue();

    $user = DB::table('users')->where('email', 'ary@app.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('ary-app')
        ->and($user->status)->toBe('active')
        ->and($user->timezone)->toBe('UTC')
        ->and($user->locale)->toBe('en');

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

    $createdUser = resolve(CreateUser::class)->handle([
        'first_name' => 'Wayan',
        'last_name' => 'Arya',
        'email' => 'wayan@app.com',
    ], 'password');

    $legacyUser = DB::table('users')->where('id', $createdUser->id)->first();

    expect($legacyUser)->not->toBeNull()
        ->and($legacyUser->name)->toBe('Wayan Arya');
});
