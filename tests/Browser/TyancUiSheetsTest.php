<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Database\Seeders\PermissionsSyncSeeder;

function browserHotFilePath(): string
{
    return public_path('hot');
}

function browserHotBackupFilePath(): string
{
    return public_path(sprintf('hot.browser-backup.%d', getmypid()));
}

/**
 * @return array<int, string>
 */
function browserHotBackupFiles(): array
{
    $backups = glob(public_path('hot.browser-backup.*'));

    return $backups === false ? [] : array_values($backups);
}

beforeEach(function (): void {
    if (file_exists(browserHotFilePath())) {
        foreach (browserHotBackupFiles() as $backupFile) {
            unlink($backupFile);
        }
    } else {
        $backupFiles = browserHotBackupFiles();

        if ($backupFiles !== []) {
            rename($backupFiles[0], browserHotFilePath());
        }
    }

    if (
        file_exists(browserHotFilePath())
        && file_exists(public_path('build/manifest.json'))
    ) {
        if (file_exists(browserHotBackupFilePath())) {
            unlink(browserHotBackupFilePath());
        }

        rename(browserHotFilePath(), browserHotBackupFilePath());
    }
});

afterEach(function (): void {
    if (
        file_exists(browserHotBackupFilePath())
        && ! file_exists(browserHotFilePath())
    ) {
        rename(browserHotBackupFilePath(), browserHotFilePath());
    }
});

function browserSettingsManager(): User
{
    $user = User::factory()->create();

    $permission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('settings', 'manage'),
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    return $user;
}

function browserRbacPermission(string $name): Permission
{
    return Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]);
}

function browserRbacManager(): User
{
    test()->seed([AppRegistrySeeder::class, PermissionsSyncSeeder::class]);

    $role = Role::query()->firstOrCreate([
        'name' => 'RBAC Manager',
        'guard_name' => 'web',
    ], [
        'level' => 50,
    ]);

    $permissions = [
        PermissionKey::tyanc('users', 'manage'),
        PermissionKey::tyanc('settings', 'manage'),
        PermissionKey::tyanc('roles', 'manage'),
        PermissionKey::tyanc('permissions', 'manage'),
        PermissionKey::tyanc('access_matrix', 'manage'),
        PermissionKey::tyanc('activity_log', 'view'),
        'demo.dashboard.viewany',
        'demo.orders.view',
        'demo.reports.view',
    ];

    $role->syncPermissions(array_map(browserRbacPermission(...), $permissions));

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('shows rem-based border radius options with the default mapped to MD', function (): void {
    $user = browserSettingsManager();

    $this->actingAs($user);

    $page = visit(route('tyanc.settings.appearance.edit'));

    $page->waitForEvent('networkidle')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('App Appearance')
        ->press('Edit appearance')
        ->wait(0.2)
        ->assertSee('Changes apply globally. Users can override via personal preferences.')
        ->assertSeeIn('#border_radius', 'MD — 0.625rem')
        ->click('#border_radius')
        ->wait(0.2)
        ->assertSee('XS — 0.125rem')
        ->assertSee('SM — 0.25rem')
        ->assertSee('MD — 0.625rem')
        ->assertSee('LG — 0.75rem')
        ->assertSee('XL — 1rem')
        ->assertSee('2XL — 1.5rem')
        ->assertDontSee('MD — 6px')
        ->assertDontSee('LG — 8px');
});

it('renders the refreshed effective access preview sheet summary and grouped sections', function (): void {
    $manager = browserRbacManager();
    $role = Role::query()->where('name', 'RBAC Manager')->firstOrFail();

    $this->actingAs($manager);

    $page = visit(route('tyanc.access-matrix.index', [
        'role_id' => $role->id,
        'app' => 'tyanc',
    ]));

    $page->waitForEvent('networkidle')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertSee('Access matrix')
        ->assertSee('Preview access')
        ->press('Preview access')
        ->wait(0.2)
        ->assertSee('Effective access preview')
        ->assertSeeIn('@effective-access-summary', 'RBAC Manager')
        ->assertSeeIn('@effective-access-summary', 'Resolved effective access')
        ->assertSeeIn('@effective-access-app-label-tyanc', 'Tyanc')
        ->assertSeeIn('@effective-access-page-label-tyanc-dashboard', 'Dashboard');
});
