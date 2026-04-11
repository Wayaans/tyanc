<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\Conversation;
use App\Models\FileLibrary;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

it('seeds realistic phase-nine demo data for tyanc modules', function (): void {
    Storage::fake('public');

    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'manuse@app.com')->first();
    $operationsLead = User::query()->where('email', 'naya.rahma@tyanc.test')->first();
    $tasksApp = App::query()->where('key', 'tasks')->first();
    $library = FileLibrary::shared();
    $conversation = Conversation::query()->where('subject', 'Phase 9 rollout checkpoint')->first();

    expect($admin)->not->toBeNull()
        ->and($admin?->hasRole((string) config('tyanc.reserved_roles.admin')))->toBeTrue()
        ->and($operationsLead)->not->toBeNull()
        ->and($operationsLead?->locale)->toBe('id')
        ->and($operationsLead?->profile?->city)->toBe('Jakarta')
        ->and(Role::query()->where('name', 'Operations Lead')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'Support Lead')->exists())->toBeTrue()
        ->and($tasksApp)->not->toBeNull()
        ->and($tasksApp?->enabled)->toBeFalse()
        ->and($tasksApp?->pages()->count())->toBe(2)
        ->and(ImportRun::query()->where('file_name', 'q2-branch-onboarding.xlsx')->exists())->toBeTrue()
        ->and(ApprovalRequest::query()->where('action', 'tyanc.users.import')->exists())->toBeTrue()
        ->and($library->getMedia(FileLibrary::FilesCollection))->toHaveCount(2)
        ->and($conversation)->not->toBeNull()
        ->and($conversation?->participants()->count())->toBe(3)
        ->and($conversation?->messages()->count())->toBe(3)
        ->and(User::query()->where('locale', 'id')->count())->toBeGreaterThanOrEqual(4)
        ->and($admin?->notifications()->count())->toBeGreaterThan(0);
});

it('keeps role and access-matrix seeders idempotent', function (): void {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        AccessMatrixSeeder::class,
    ]);

    $roleCount = Role::query()->count();
    $admin = Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail();
    $adminPermissionCount = $admin->permissions()->count();

    $this->seed([
        RolesAndPermissionsSeeder::class,
        AccessMatrixSeeder::class,
    ]);

    expect(Role::query()->count())->toBe($roleCount)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->firstOrFail()->permissions()->count())->toBeGreaterThan(0)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail()->permissions()->count())->toBe($adminPermissionCount);
});
