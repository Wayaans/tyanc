<?php

declare(strict_types=1);

use App\Actions\ResolveTranslations;
use App\Actions\Settings\ResolveRuntimeSettings;
use App\Actions\Tyanc\Access\ResolveAccessibleApps;
use App\Actions\Tyanc\Messaging\CountUnreadMessages;
use App\Actions\Tyanc\Messaging\ListRecentConversations;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

function inertiaMiddleware(): HandleInertiaRequests
{
    return new HandleInertiaRequests(
        runtimeSettings: resolve(ResolveRuntimeSettings::class),
        translations: resolve(ResolveTranslations::class),
        accessibleApps: resolve(ResolveAccessibleApps::class),
        unreadMessages: resolve(CountUnreadMessages::class),
        recentConversations: resolve(ListRecentConversations::class),
    );
}

it('shares the expected top-level inertia props', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)
        ->toHaveKeys([
            'name',
            'brand',
            'theme',
            'locale',
            'availableLocales',
            'translations',
            'userPreferences',
            'auth',
            'notifications',
            'messages',
            'messagesUnreadCount',
            'accessibleApps',
            'currentApp',
            'sidebarNavigation',
            'sidebarOpen',
        ]);
});

it('shares empty app navigation for guests on the home page', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)->toHaveKey('currentApp')
        ->and($shared['currentApp'])->toBe('tyanc')
        ->and($shared['accessibleApps'])->toBe([])
        ->and($shared['sidebarNavigation']['apps'])->toBe([])
        ->and($shared['sidebarNavigation']['menu'])->toBe([])
        ->and($shared['theme'])->toMatchArray([
            'appearance' => 'system',
            'sidebar_variant' => 'inset',
            'spacing_density' => 'default',
        ]);
});

it('prefers the current app cookie on shared routes', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => 'demo.dashboard.viewany',
        'guard_name' => 'web',
    ]));

    $request = Request::create('/settings/account', 'GET');
    $request->cookies->set('current_app', 'demo');
    $request->setUserResolver(fn (): User => $user);

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('demo');
});

it('falls back to tyanc when the current app cookie is invalid', function (): void {
    $request = Request::create('/settings/account', 'GET');
    $request->cookies->set('current_app', 'invalid');

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('tyanc');
});

it('defaults sidebarOpen to true when no cookie', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)->toHaveKey('sidebarOpen')
        ->and($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to true when cookie is true', function (): void {
    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'true');

    $shared = inertiaMiddleware()->share($request);

    expect($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to false when cookie is false', function (): void {
    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'false');

    $shared = inertiaMiddleware()->share($request);

    expect($shared['sidebarOpen'])->toBeFalse();
});

it('shares demo as the current app for demo routes', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->firstOrCreate([
        'name' => 'demo.dashboard.viewany',
        'guard_name' => 'web',
    ]));

    $request = Request::create('/demo/dashboard', 'GET');
    $request->cookies->set('current_app', 'tyanc');
    $request->setUserResolver(fn (): User => $user);

    $route = new Route('GET', '/demo/dashboard', fn (): null => null);
    $route->name('demo.dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('demo')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/demo/dashboard');
});

it('shares tyanc as the current app for the tyanc dashboard route', function (): void {
    $user = User::factory()->create();

    $request = Request::create('/tyanc/dashboard', 'GET');
    $request->cookies->set('current_app', 'demo');
    $request->setUserResolver(fn (): User => $user);

    $route = new Route('GET', '/tyanc/dashboard', fn (): null => null);
    $route->name('dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('tyanc');
});
