<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\ApprovalRequest;
use App\Models\Conversation;
use App\Models\ImportRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

it('bootstraps production metadata without creating demo or human users', function (): void {
    $this->artisan('tyanc:bootstrap')
        ->assertSuccessful();

    $adminRole = Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail();

    expect(User::query()->count())->toBe(0)
        ->and(App::query()->where('key', 'tyanc')->where('is_system', true)->exists())->toBeTrue()
        ->and(App::query()->where('key', 'demo')->where('is_system', false)->exists())->toBeTrue()
        ->and(Role::query()->orderByDesc('level')->pluck('name')->all())->toBe([
            (string) config('tyanc.reserved_roles.super_admin'),
            (string) config('tyanc.reserved_roles.admin'),
        ])
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->firstOrFail()->permissions()->count())->toBe(0)
        ->and($adminRole->permissions()->pluck('name')->every(fn (string $permission): bool => str_starts_with($permission, 'tyanc.')))->toBeTrue()
        ->and(Permission::query()->whereIn('name', PermissionKey::all())->count())->toBe(count(PermissionKey::all()))
        ->and(ImportRun::query()->count())->toBe(0)
        ->and(ApprovalRequest::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0);
});

it('keeps the production bootstrap command idempotent', function (): void {
    $this->artisan('tyanc:bootstrap')->assertSuccessful();

    $roleCount = Role::query()->count();
    $appCount = App::query()->count();
    $permissionCount = Permission::query()->count();
    $adminPermissionCount = Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail()->permissions()->count();

    $this->artisan('tyanc:bootstrap')->assertSuccessful();

    expect(Role::query()->count())->toBe($roleCount)
        ->and(App::query()->count())->toBe($appCount)
        ->and(Permission::query()->count())->toBe($permissionCount)
        ->and(User::query()->count())->toBe(0)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->firstOrFail()->permissions()->count())->toBe(0)
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->firstOrFail()->permissions()->count())->toBe($adminPermissionCount);
});
