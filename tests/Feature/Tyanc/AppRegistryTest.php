<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\AppPage;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function appRegistryManager(): User
{
    $user = User::factory()->create();

    $permission = Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('apps', 'manage'),
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);

    return $user;
}

it('lists the app registry for authorized managers', function (): void {
    $manager = appRegistryManager();

    $this->seed(AppRegistrySeeder::class);

    $this->actingAs($manager)
        ->get(route('tyanc.apps.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/apps/Index')
            ->where('apps.0.key', 'tyanc')
            ->where('apps.1.key', 'demo'));

    $this->actingAs($manager)
        ->getJson(route('tyanc.apps.index'))
        ->assertOk()
        ->assertJsonCount(2, 'apps')
        ->assertJsonPath('apps.0.key', 'tyanc')
        ->assertJsonPath('apps.0.pages.0.key', 'dashboard')
        ->assertJsonPath('apps.1.key', 'demo');
});

it('does not mutate app state when listing the registry', function (): void {
    $manager = appRegistryManager();

    $this->seed(AppRegistrySeeder::class);

    DB::table('apps')
        ->where('key', 'tyanc')
        ->update([
            'is_system' => false,
            'updated_at' => now(),
        ]);

    $this->actingAs($manager)
        ->getJson(route('tyanc.apps.index'))
        ->assertOk();

    expect(App::query()->where('key', 'tyanc')->value('is_system'))->toBeFalse();
});

it('renders full-page app create and edit screens for authorized managers', function (): void {
    $manager = appRegistryManager();

    $app = App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
    ]);

    AppPage::factory()->for($app, 'app')->create([
        'key' => 'dashboard',
        'label' => 'Dashboard',
        'path' => '/tasks/dashboard',
        'permission_name' => 'tasks.dashboard.viewany',
    ]);

    $this->actingAs($manager)
        ->get(route('tyanc.apps.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tyanc/apps/Create'));

    $this->actingAs($manager)
        ->get(route('tyanc.apps.edit', $app))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tyanc/apps/Edit')
            ->where('app.key', 'tasks')
            ->where('app.pages.0.key', 'dashboard'));
});

it('registers an app with managed pages', function (): void {
    $manager = appRegistryManager();

    $this->actingAs($manager)
        ->postJson(route('tyanc.apps.store'), [
            'key' => 'erp',
            'label' => 'ERP',
            'route_prefix' => 'erp',
            'icon' => 'layout-grid',
            'permission_namespace' => 'erp',
            'enabled' => true,
            'sort_order' => 30,
            'pages' => [
                [
                    'key' => 'dashboard',
                    'label' => 'Dashboard',
                    'route_name' => null,
                    'path' => '/erp/dashboard',
                    'permission_name' => 'erp.dashboard.viewany',
                    'sort_order' => 0,
                    'enabled' => true,
                    'is_navigation' => true,
                ],
                [
                    'key' => 'orders',
                    'label' => 'Orders',
                    'path' => '/erp/orders',
                    'permission_name' => 'erp.orders.viewany',
                    'sort_order' => 1,
                    'enabled' => true,
                    'is_navigation' => true,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('app.key', 'erp')
        ->assertJsonPath('app.pages.0.key', 'dashboard')
        ->assertJsonPath('app.pages.1.key', 'orders');

    $app = App::query()->where('key', 'erp')->first();

    expect($app)->not->toBeNull()
        ->and($app->route_prefix)->toBe('erp')
        ->and($app->permission_namespace)->toBe('erp')
        ->and($app->pages()->count())->toBe(2)
        ->and($app->pages()->where('permission_name', 'erp.orders.viewany')->exists())->toBeTrue();
});

it('updates custom apps and replaces managed pages', function (): void {
    $manager = appRegistryManager();

    $app = App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
    ]);

    AppPage::factory()->for($app, 'app')->create([
        'key' => 'backlog',
        'label' => 'Backlog',
        'path' => '/tasks/backlog',
    ]);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.apps.update', $app), [
            'key' => 'tasks',
            'label' => 'Task Workspace',
            'route_prefix' => 'tasks-app',
            'icon' => 'settings',
            'permission_namespace' => 'tasks',
            'enabled' => true,
            'sort_order' => 60,
            'pages' => [
                [
                    'key' => 'board',
                    'label' => 'Board',
                    'path' => '/tasks-app/board',
                    'permission_name' => 'tasks.board.viewany',
                    'sort_order' => 0,
                    'enabled' => true,
                    'is_navigation' => true,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('app.label', 'Task Workspace')
        ->assertJsonPath('app.pages.0.key', 'board');

    $app->refresh();

    expect($app->label)->toBe('Task Workspace')
        ->and($app->route_prefix)->toBe('tasks-app')
        ->and($app->pages()->where('key', 'backlog')->exists())->toBeFalse()
        ->and($app->pages()->where('key', 'board')->exists())->toBeTrue();
});

it('keeps existing pages when app metadata updates omit pages', function (): void {
    $manager = appRegistryManager();

    $app = App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
    ]);

    AppPage::factory()->for($app, 'app')->create([
        'key' => 'backlog',
        'label' => 'Backlog',
        'path' => '/tasks/backlog',
        'permission_name' => 'tasks.backlog.viewany',
    ]);

    $this->actingAs($manager)
        ->patchJson(route('tyanc.apps.update', $app), [
            'key' => 'tasks',
            'label' => 'Task Workspace',
            'route_prefix' => 'tasks',
            'icon' => 'settings',
            'permission_namespace' => 'tasks',
            'enabled' => true,
            'sort_order' => 60,
        ])
        ->assertOk()
        ->assertJsonPath('app.label', 'Task Workspace')
        ->assertJsonPath('app.pages.0.key', 'backlog');

    expect($app->fresh()->pages()->where('key', 'backlog')->exists())->toBeTrue();
});

it('forbids app registry management without the namespaced app permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('tyanc.apps.index'))
        ->assertForbidden();
});

it('keeps tyanc protected while allowing demo to be replaced', function (): void {
    $manager = appRegistryManager();

    $this->seed(AppRegistrySeeder::class);

    $tyanc = App::query()->where('key', 'tyanc')->firstOrFail();
    $demo = App::query()->where('key', 'demo')->firstOrFail();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.apps.toggle', $tyanc), ['enabled' => false])
        ->assertForbidden();

    $this->actingAs($manager)
        ->patchJson(route('tyanc.apps.toggle', $demo), ['enabled' => false])
        ->assertOk();

    $this->actingAs($manager)
        ->deleteJson(route('tyanc.apps.destroy', $demo))
        ->assertNoContent();

    expect($tyanc->fresh()->enabled)->toBeTrue()
        ->and(App::query()->where('key', 'demo')->exists())->toBeFalse();
});

it('shares only enabled and accessible apps on shared routes and falls back when cookie access is revoked', function (): void {
    App::query()->updateOrCreate(
        ['key' => 'tyanc'],
        [
            'label' => 'Tyanc',
            'route_prefix' => 'tyanc',
            'icon' => 'app-logo',
            'permission_namespace' => 'tyanc',
            'enabled' => true,
            'sort_order' => 0,
            'is_system' => true,
        ],
    );

    App::query()->updateOrCreate(
        ['key' => 'demo'],
        [
            'label' => 'Demo',
            'route_prefix' => 'demo',
            'icon' => 'flask-conical',
            'permission_namespace' => 'demo',
            'enabled' => true,
            'sort_order' => 10,
            'is_system' => true,
        ],
    );

    DB::table('apps')
        ->where('key', 'demo')
        ->update(['enabled' => false, 'updated_at' => now()]);

    $erp = App::query()->create([
        'key' => 'erp',
        'label' => 'ERP',
        'route_prefix' => 'erp',
        'icon' => 'layout-grid',
        'permission_namespace' => 'erp',
        'enabled' => true,
        'sort_order' => 20,
        'is_system' => false,
    ]);

    AppPage::factory()->for($erp, 'app')->create([
        'key' => 'dashboard',
        'label' => 'Dashboard',
        'path' => '/erp/dashboard',
        'permission_name' => 'erp.dashboard.viewany',
    ]);

    Permission::query()->create([
        'name' => 'erp.dashboard.viewany',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withCookie('current_app', 'demo')
        ->get(route('user-profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/Edit')
            ->where('currentApp', 'tyanc')
            ->has('accessibleApps', 1)
            ->where('accessibleApps.0.key', 'tyanc')
            ->where('sidebarNavigation.apps.0.id', 'tyanc'));

    $user->givePermissionTo('erp.dashboard.viewany');

    $this->actingAs($user)
        ->withCookie('current_app', 'erp')
        ->get(route('user-profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/Edit')
            ->where('currentApp', 'erp')
            ->has('accessibleApps', 2)
            ->where('accessibleApps.1.key', 'erp')
            ->where('sidebarNavigation.apps.1.id', 'erp'));
});

it('does not fall back to configured apps when all registry apps are disabled', function (): void {
    $this->seed(AppRegistrySeeder::class);

    DB::table('apps')->update([
        'enabled' => false,
        'updated_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('user-profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/Edit')
            ->where('accessibleApps', [])
            ->where('sidebarNavigation.apps', [])
            ->where('sidebarNavigation.menu', []));
});

it('seeds the app registry before sharing accessible apps so protected demo pages stay hidden', function (): void {
    $user = User::factory()->create();

    expect(AppPage::query()->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('user-profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/Edit')
            ->where('currentApp', 'tyanc')
            ->has('accessibleApps', 1)
            ->where('accessibleApps.0.key', 'tyanc')
            ->where('sidebarNavigation.apps.0.id', 'tyanc'));

    expect(App::query()->where('key', 'demo')->exists())->toBeTrue()
        ->and(AppPage::query()->where('route_name', 'demo.dashboard')->where('permission_name', 'demo.dashboard.viewany')->exists())->toBeTrue();
});

it('does not expose protected apps to guests through shared navigation props', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('currentApp', 'tyanc')
            ->where('accessibleApps', [])
            ->where('sidebarNavigation.apps', [])
            ->where('sidebarNavigation.menu', []));
});

it('shows manage-gated sidebar items for authenticated users with the matching permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => PermissionKey::tyanc('users', 'manage'),
        'guard_name' => 'web',
    ]));

    $this->actingAs($user)
        ->get(route('user-profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/Edit')
            ->where('sidebarNavigation.menu.1.title', 'Users')
            ->where('sidebarNavigation.menu.1.href', '/tyanc/users'));
});

it('blocks disabled app routes even when the user has the page permission', function (): void {
    $this->seed(AppRegistrySeeder::class);

    DB::table('apps')
        ->where('key', 'demo')
        ->update(['enabled' => false, 'updated_at' => now()]);

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => 'demo.dashboard.viewany',
        'guard_name' => 'web',
    ]));

    $this->actingAs($user)
        ->get(route('demo.dashboard'))
        ->assertNotFound();
});

it('denies direct access to registered app routes that are missing an app-page record', function (): void {
    $app = App::factory()->create([
        'key' => 'tasks',
        'label' => 'Tasks',
        'route_prefix' => 'tasks',
        'permission_namespace' => 'tasks',
    ]);

    AppPage::factory()->for($app, 'app')->create([
        'key' => 'dashboard',
        'label' => 'Dashboard',
        'path' => '/tasks/dashboard',
        'permission_name' => null,
    ]);

    Route::middleware(['web', 'auth'])
        ->get('/tasks/hidden-report', fn (): string => 'secret')
        ->name('tasks.hidden-report');

    $this->actingAs(User::factory()->create())
        ->get('/tasks/hidden-report')
        ->assertForbidden();
});

it('preserves customized app state when the registry seeder is re-run', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $demo = App::query()->where('key', 'demo')->firstOrFail();

    $demo->forceFill([
        'label' => 'Sandbox Workspace',
        'route_prefix' => 'sandbox',
        'icon' => 'settings',
        'permission_namespace' => 'sandbox',
        'enabled' => false,
        'sort_order' => 90,
        'is_system' => false,
    ])->save();

    $demo->pages()->delete();

    AppPage::factory()->for($demo, 'app')->create([
        'key' => 'home',
        'label' => 'Home',
        'route_name' => null,
        'path' => '/sandbox/home',
        'permission_name' => null,
        'is_system' => false,
    ]);

    $this->seed(AppRegistrySeeder::class);

    $demo->refresh();

    expect($demo->label)->toBe('Sandbox Workspace')
        ->and($demo->route_prefix)->toBe('sandbox')
        ->and($demo->icon)->toBe('settings')
        ->and($demo->permission_namespace)->toBe('sandbox')
        ->and($demo->enabled)->toBeFalse()
        ->and($demo->sort_order)->toBe(90)
        ->and($demo->is_system)->toBeFalse()
        ->and($demo->pages()->count())->toBe(1)
        ->and($demo->pages()->where('key', 'home')->exists())->toBeTrue()
        ->and($demo->pages()->where('route_name', 'demo.dashboard')->exists())->toBeFalse();
});

it('restores missing default registry pages when the default app identity is still managed', function (): void {
    $this->seed(AppRegistrySeeder::class);

    $tyanc = App::query()->where('key', 'tyanc')->firstOrFail();

    $tyanc->pages()->where('route_name', 'tyanc.users.index')->delete();

    expect($tyanc->pages()->where('route_name', 'tyanc.users.index')->exists())->toBeFalse();

    $this->seed(AppRegistrySeeder::class);

    expect($tyanc->fresh()->pages()->where('route_name', 'tyanc.users.index')->exists())->toBeTrue();
});
