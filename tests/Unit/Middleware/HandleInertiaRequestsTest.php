<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

function inertiaMiddleware(): HandleInertiaRequests
{
    return resolve(HandleInertiaRequests::class);
}

it('shares the resolved app name from runtime settings', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)->toHaveKey('name')
        ->and($shared['name'])->toBe('Tyanc')
        ->and($shared['brand'])->toMatchArray([
            'app_name' => 'Tyanc',
            'company_legal_name' => 'Tyanc',
        ]);
});

it('shares null user when guest', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)->toHaveKey('auth')
        ->and($shared['auth'])->toHaveKey('user')
        ->and($shared['auth']['user'])->toBeNull();
});

it('shares authenticated user data as a dto', function (): void {
    $user = User::factory()->create([
        'username' => 'test-user',
        'email' => 'test@example.com',
    ]);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn (): User => $user);

    $shared = inertiaMiddleware()->share($request);

    expect($shared['auth']['user'])->not->toBeNull()
        ->and($shared['auth']['user']->id)->toBe($user->id)
        ->and($shared['auth']['user']->name)->toBe('test-user')
        ->and($shared['auth']['user']->username)->toBe('test-user')
        ->and($shared['auth']['user']->email)->toBe('test@example.com');
});

it('shares tyanc as the default app', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)->toHaveKey('currentApp')
        ->and($shared['currentApp'])->toBe('tyanc')
        ->and($shared['sidebarNavigation']['apps'])->toBeArray()
        ->and($shared['sidebarNavigation']['apps'][0]['href'])->toBe('/tyanc/dashboard')
        ->and($shared['sidebarNavigation']['menu'][0]['title'])->toBe('Dashboard')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/tyanc/dashboard')
        ->and($shared['sidebarNavigation']['menu'][1]['title'])->toBe('User')
        ->and($shared['sidebarNavigation']['menu'][1])->not->toHaveKey('href')
        ->and($shared['sidebarNavigation']['menu'][2]['title'])->toBe('Role & Permission')
        ->and($shared['sidebarNavigation']['menu'][2]['children'][0]['title'])->toBe('Role')
        ->and($shared['sidebarNavigation']['menu'][3]['title'])->toBe('App Settings')
        ->and($shared['sidebarNavigation']['menu'][3]['href'])->toBe('/tyanc/settings')
        ->and($shared['sidebarNavigation']['menu'][3])->not->toHaveKey('children')
        ->and($shared['theme'])->toMatchArray([
            'appearance' => 'system',
            'sidebar_variant' => 'inset',
            'spacing_density' => 'default',
        ]);
});

it('prefers the current app cookie on shared routes', function (): void {
    $request = Request::create('/settings/profile', 'GET');
    $request->cookies->set('current_app', 'demo');

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('demo');
});

it('falls back to tyanc when the current app cookie is invalid', function (): void {
    $request = Request::create('/settings/profile', 'GET');
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
    $request = Request::create('/demo/dashboard', 'GET');
    $request->cookies->set('current_app', 'tyanc');

    $route = new Route('GET', '/demo/dashboard', fn (): null => null);
    $route->name('demo.dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('demo')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/demo/dashboard');
});

it('shares tyanc as the current app for the tyanc dashboard route', function (): void {
    $request = Request::create('/tyanc/dashboard', 'GET');
    $request->cookies->set('current_app', 'demo');

    $route = new Route('GET', '/tyanc/dashboard', fn (): null => null);
    $route->name('dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $shared = inertiaMiddleware()->share($request);

    expect($shared['currentApp'])->toBe('tyanc')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/tyanc/dashboard');
});

it('shares resolved user preferences and theme overrides', function (): void {
    $user = User::factory()->create([
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
        'timezone' => 'Asia/Makassar',
        'appearance' => 'dark',
        'sidebar_variant' => 'floating',
        'spacing_density' => 'comfortable',
    ]);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn (): User => $user);
    $request->cookies->set('appearance', 'light');

    $shared = inertiaMiddleware()->share($request);

    expect($shared['userPreferences']->resolved_locale)->toBe('en')
        ->and($shared['userPreferences']->resolved_timezone)->toBe('UTC')
        ->and($shared['userPreferences']->resolved_appearance)->toBe('dark')
        ->and($shared['theme'])->toMatchArray([
            'appearance' => 'dark',
            'sidebar_variant' => 'floating',
            'spacing_density' => 'comfortable',
            'spacing_density_value' => 1.25,
        ]);
});

it('includes parent shared data', function (): void {
    $request = Request::create('/', 'GET');

    $shared = inertiaMiddleware()->share($request);

    expect($shared)->toHaveKey('errors');
});
