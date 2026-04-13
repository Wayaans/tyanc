<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\AppPage;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

it('fails closed on tyanc routes without mutating the app registry', function (): void {
    $user = User::factory()->create();
    $appCount = App::query()->count();
    $pageCount = AppPage::query()->count();

    $this->actingAs($user)
        ->get(route('tyanc.users.index'))
        ->assertStatus(503)
        ->assertInertia(fn ($page) => $page
            ->component('errors/BootstrapIncomplete')
            ->where('missing', function ($missing): bool {
                $items = collect($missing);
                if ($items->contains('apps.tyanc')) {
                    return true;
                }

                return $items->contains(fn (string $item): bool => str_starts_with($item, 'app_pages.tyanc.'));
            }));

    expect(App::query()->count())->toBe($appCount)
        ->and(AppPage::query()->count())->toBe($pageCount);
});

it('fails closed on configured app routes without mutating the app registry', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => PermissionKey::make('demo', 'dashboard', 'viewany'),
        'guard_name' => 'web',
    ]));

    $appCount = App::query()->count();
    $pageCount = AppPage::query()->count();

    $this->actingAs($user)
        ->get(route('demo.dashboard'))
        ->assertStatus(503)
        ->assertInertia(fn ($page) => $page
            ->component('errors/BootstrapIncomplete')
            ->where('missing', function ($missing): bool {
                $items = collect($missing);
                if ($items->contains('apps.demo')) {
                    return true;
                }

                return $items->contains(fn (string $item): bool => str_starts_with($item, 'app_pages.demo.'));
            }));

    expect(App::query()->count())->toBe($appCount)
        ->and(AppPage::query()->count())->toBe($pageCount);
});

it('keeps shared authenticated routes read only when bootstrap metadata is missing', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => PermissionKey::make('demo', 'dashboard', 'viewany'),
        'guard_name' => 'web',
    ]));

    $appCount = App::query()->count();
    $pageCount = AppPage::query()->count();

    $this->actingAs($user)
        ->get(route('settings.account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Account')
            ->where('accessibleApps', [])
            ->where('sidebarNavigation.apps', []));

    expect(App::query()->count())->toBe($appCount)
        ->and(AppPage::query()->count())->toBe($pageCount);
});
