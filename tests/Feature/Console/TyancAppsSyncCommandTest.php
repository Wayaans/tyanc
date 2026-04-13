<?php

declare(strict_types=1);

use App\Models\App;
use App\Models\AppPage;

it('syncs configured apps and managed app pages through the artisan command', function (): void {
    $this->artisan('tyanc:apps-sync')
        ->assertSuccessful();

    expect(App::query()->orderBy('sort_order')->pluck('key')->all())->toBe(['tyanc', 'cumpu', 'demo'])
        ->and(AppPage::query()->where('route_name', 'dashboard')->exists())->toBeTrue()
        ->and(AppPage::query()->where('route_name', 'tyanc.users.index')->exists())->toBeTrue()
        ->and(AppPage::query()->where('route_name', 'cumpu.approvals.index')->exists())->toBeTrue();
});

it('preserves customized app identity when the apps sync command is re-run', function (): void {
    $this->artisan('tyanc:apps-sync')->assertSuccessful();

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

    AppPage::query()->create([
        'app_id' => $demo->id,
        'key' => 'home',
        'label' => 'Home',
        'route_name' => null,
        'path' => '/sandbox/home',
        'permission_name' => null,
        'sort_order' => 0,
        'enabled' => true,
        'is_navigation' => true,
        'is_system' => false,
    ]);

    $this->artisan('tyanc:apps-sync')->assertSuccessful();

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

it('restores missing managed default pages when the apps sync command is re-run', function (): void {
    $this->artisan('tyanc:apps-sync')->assertSuccessful();

    $tyanc = App::query()->where('key', 'tyanc')->firstOrFail();

    $tyanc->pages()->where('route_name', 'tyanc.users.index')->delete();

    expect($tyanc->pages()->where('route_name', 'tyanc.users.index')->exists())->toBeFalse();

    $this->artisan('tyanc:apps-sync')->assertSuccessful();

    expect($tyanc->fresh()->pages()->where('route_name', 'tyanc.users.index')->exists())->toBeTrue();
});
