<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

it('shares app name from config', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('name')
        ->and($shared['name'])->toBe(config('app.name'));
});

it('shares null user when guest', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('auth')
        ->and($shared['auth'])->toHaveKey('user')
        ->and($shared['auth']['user'])->toBeNull();
});

it('shares authenticated user data', function (): void {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect($shared['auth']['user'])->not->toBeNull()
        ->and($shared['auth']['user']->id)->toBe($user->id)
        ->and($shared['auth']['user']->name)->toBe('Test User')
        ->and($shared['auth']['user']->email)->toBe('test@example.com');
});

it('shares tyanc as the default app', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('currentApp')
        ->and($shared['currentApp'])->toBe('tyanc')
        ->and($shared['sidebarNavigation']['apps'])->toBeArray()
        ->and($shared['sidebarNavigation']['apps'][0]['href'])->toBe('/tyanc/dashboard')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/tyanc/dashboard');
});

it('prefers the current app cookie on shared routes', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/settings/profile', 'GET');
    $request->cookies->set('current_app', 'demo');

    $shared = $middleware->share($request);

    expect($shared['currentApp'])->toBe('demo');
});

it('falls back to tyanc when the current app cookie is invalid', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/settings/profile', 'GET');
    $request->cookies->set('current_app', 'invalid');

    $shared = $middleware->share($request);

    expect($shared['currentApp'])->toBe('tyanc');
});

it('defaults sidebarOpen to true when no cookie', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('sidebarOpen')
        ->and($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to true when cookie is true', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'true');

    $shared = $middleware->share($request);

    expect($shared['sidebarOpen'])->toBeTrue();
});

it('sets sidebarOpen to false when cookie is false', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');
    $request->cookies->set('sidebar_state', 'false');

    $shared = $middleware->share($request);

    expect($shared['sidebarOpen'])->toBeFalse();
});

it('shares demo as the current app for demo routes', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/demo/dashboard', 'GET');
    $request->cookies->set('current_app', 'tyanc');

    $route = new Route('GET', '/demo/dashboard', fn (): null => null);
    $route->name('demo.dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $shared = $middleware->share($request);

    expect($shared['currentApp'])->toBe('demo')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/demo/dashboard');
});

it('shares tyanc as the current app for the tyanc dashboard route', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/tyanc/dashboard', 'GET');
    $request->cookies->set('current_app', 'demo');

    $route = new Route('GET', '/tyanc/dashboard', fn (): null => null);
    $route->name('dashboard');

    $request->setRouteResolver(fn (): Route => $route);

    $shared = $middleware->share($request);

    expect($shared['currentApp'])->toBe('tyanc')
        ->and($shared['sidebarNavigation']['menu'][0]['href'])->toBe('/tyanc/dashboard');
});

it('includes parent shared data', function (): void {
    $middleware = new HandleInertiaRequests();

    $request = Request::create('/', 'GET');

    $shared = $middleware->share($request);

    // Parent Inertia middleware shares 'errors' by default
    expect($shared)->toHaveKey('errors');
});
