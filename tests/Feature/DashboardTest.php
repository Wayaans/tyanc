<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\App;
use App\Models\FileLibrary;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Database\Seeders\PermissionsSyncSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function tyancDashboardManager(): User
{
    test()->seed([AppRegistrySeeder::class, PermissionsSyncSeeder::class]);
    Storage::fake('public');

    $manager = User::factory()->create([
        'name' => 'Dashboard Manager',
        'email' => 'dashboard.manager@example.com',
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'last_login_at' => now()->subHour(),
    ]);

    $manager->givePermissionTo([
        PermissionKey::tyanc('users', 'viewany'),
        PermissionKey::tyanc('roles', 'viewany'),
        PermissionKey::tyanc('permissions', 'viewany'),
        PermissionKey::tyanc('files', 'viewany'),
        PermissionKey::tyanc('apps', 'viewany'),
        PermissionKey::tyanc('messages', 'viewany'),
        PermissionKey::tyanc('activity_log', 'viewany'),
    ]);

    $governanceLead = Role::query()->create([
        'name' => 'Governance Lead',
        'guard_name' => 'web',
        'level' => 80,
    ]);
    $governanceLead->givePermissionTo([
        PermissionKey::tyanc('users', 'viewany'),
        PermissionKey::tyanc('roles', 'viewany'),
    ]);

    $observer = Role::query()->create([
        'name' => 'Observer',
        'guard_name' => 'web',
        'level' => 20,
    ]);

    User::factory()->create([
        'name' => 'Pending User',
        'email' => 'pending.user@example.com',
        'status' => UserStatus::PendingVerification,
        'email_verified_at' => null,
    ])->assignRole($governanceLead);

    User::factory()->create([
        'name' => 'Suspended User',
        'email' => 'suspended.user@example.com',
        'status' => UserStatus::Suspended,
    ])->assignRole($observer);

    User::factory()->create([
        'name' => 'Platform User',
        'email' => 'platform.user@example.com',
        'status' => UserStatus::Active,
        'last_login_at' => now()->subMinutes(30),
    ])->assignRole($governanceLead);

    Permission::query()->create([
        'name' => 'tyanc.experimental.review',
        'guard_name' => 'web',
    ]);

    $library = FileLibrary::shared();
    $library
        ->addMedia(UploadedFile::fake()->image('policy.png'))
        ->withCustomProperties([
            'uploaded_by_id' => (string) $manager->id,
            'uploaded_by_name' => $manager->name,
        ])
        ->toMediaCollection(FileLibrary::FilesCollection);
    $library
        ->addMedia(UploadedFile::fake()->create('access-matrix.pdf', 12, 'application/pdf'))
        ->withCustomProperties([
            'uploaded_by_id' => (string) $manager->id,
            'uploaded_by_name' => $manager->name,
        ])
        ->toMediaCollection(FileLibrary::FilesCollection);

    App::query()->where('key', 'demo')->update([
        'enabled' => false,
    ]);

    return $manager->fresh();
}

it('renders the tyanc dashboard from live tyanc data', function (): void {
    $manager = tyancDashboardManager();

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/Dashboard')
            ->where('currentApp', 'tyanc')
            ->where('summary.module_count', 5)
            ->where('summary.healthy_count', 1)
            ->where('summary.monitoring_count', 1)
            ->where('summary.attention_count', 3)
            ->where('modules', fn ($modules): bool => collect($modules)->pluck('key')->all() === ['users', 'roles', 'permissions', 'files', 'apps'])
            ->where('users.total', 4)
            ->where('users.pending_verification', 1)
            ->where('users.suspended', 1)
            ->where('roles.total', 2)
            ->where('roles.without_permissions', 1)
            ->where('permissions.orphaned', 1)
            ->where('files.total', 2)
            ->where('apps.total', 3)
            ->where('apps.disabled', 1)
            ->where('abilities.users', true)
            ->where('abilities.roles', true)
            ->where('abilities.permissions', true)
            ->where('abilities.files', true)
            ->where('abilities.apps', true)
            ->where('abilities.messages', true)
            ->where('abilities.activity_log', true)
            ->where('alerts', fn ($alerts): bool => collect($alerts)->pluck('key')->contains('flagged-users')
                && collect($alerts)->pluck('key')->contains('roles-without-permissions')
                && collect($alerts)->pluck('key')->contains('permission-sync-drift')
                && collect($alerts)->pluck('key')->contains('disabled-apps'))
            ->where('sidebarNavigation.apps.0.href', '/tyanc/dashboard')
            ->where('sidebarNavigation.menu.0.href', '/tyanc/dashboard'));
});

it('forbids the demo dashboard without the configured page permission', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('demo.dashboard'))
        ->assertForbidden();
});

it('renders the demo dashboard when the page permission is granted directly', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = User::factory()->create();

    $permission = Permission::query()->firstOrCreate([
        'name' => 'demo.dashboard.viewany',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)->get(route('demo.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('demo/Dashboard')
            ->where('currentApp', 'demo')
            ->where('examplesTable.meta.total', 5)
            ->where('sidebarNavigation.apps.1.href', '/demo/dashboard')
            ->where('sidebarNavigation.menu.0.href', '/demo/dashboard'));
});

it('treats manage as access to demo dashboard page visibility', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $user = User::factory()->create();

    $managePermission = Permission::query()->firstOrCreate([
        'name' => 'demo.dashboard.manage',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($managePermission);

    $this->actingAs($user)
        ->get(route('demo.dashboard'))
        ->assertOk();
});

it('returns live tyanc dashboard metrics from the current database state', function (): void {
    $manager = tyancDashboardManager();

    $this->actingAs($manager)
        ->getJson(route('dashboard'))
        ->assertOk()
        ->assertJsonPath('summary.attention_count', 3)
        ->assertJsonPath('modules.0.key', 'users')
        ->assertJsonPath('modules.0.status', 'Attention')
        ->assertJsonPath('modules.1.key', 'roles')
        ->assertJsonPath('modules.1.status', 'Attention')
        ->assertJsonPath('modules.2.key', 'permissions')
        ->assertJsonPath('modules.2.status', 'Attention')
        ->assertJsonPath('modules.3.key', 'files')
        ->assertJsonPath('modules.3.status', 'Healthy')
        ->assertJsonPath('modules.4.key', 'apps')
        ->assertJsonPath('modules.4.status', 'Monitoring')
        ->assertJsonPath('permissions.orphaned', 1)
        ->assertJsonPath('apps.recent.2.key', 'demo')
        ->assertJson(fn ($json) => $json
            ->where('users.recent', fn ($users): bool => collect($users)->pluck('name')->contains('Platform User'))
            ->where('files.recent', fn ($files): bool => collect($files)->pluck('file_name')->contains('access-matrix.pdf'))
            ->etc());
});
