<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Console\Command;

it('reports bootstrap readiness before and after running the production bootstrap command', function (): void {
    $this->artisan('tyanc:bootstrap-check')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('tyanc:bootstrap')
        ->assertSuccessful();

    $this->artisan('tyanc:bootstrap-check')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(App::query()->where('key', 'tyanc')->exists())->toBeTrue()
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.super_admin'))->exists())->toBeTrue()
        ->and(Role::query()->where('name', (string) config('tyanc.reserved_roles.admin'))->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', PermissionKey::all())->count())->toBe(count(PermissionKey::all()));
});

it('returns successful json summaries for the production bootstrap commands', function (): void {
    $this->artisan('tyanc:bootstrap', ['--json' => true])
        ->assertSuccessful();

    $this->artisan('tyanc:bootstrap-check', ['--json' => true])
        ->assertSuccessful();
});
