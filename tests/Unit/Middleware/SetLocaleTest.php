<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\SessionManager;

it('uses the user preference locale when available', function (): void {
    $user = User::factory()->create(['locale' => 'en']);

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
    ]);

    $middleware = resolve(SetLocale::class);
    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn (): User => $user);
    $request->setLaravelSession(resolve(SessionManager::class)->driver());

    $middleware->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('id')
        ->and($request->getLocale())->toBe('id');
});

it('falls back to the authenticated user locale before session locale', function (): void {
    $user = User::factory()->create(['locale' => 'id']);

    $middleware = resolve(SetLocale::class);
    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn (): User => $user);
    $request->setLaravelSession(resolve(SessionManager::class)->driver());
    $request->session()->put('locale', 'en');

    $middleware->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('id');
});

it('uses the session locale for guests', function (): void {
    $middleware = resolve(SetLocale::class);
    $request = Request::create('/', 'GET');
    $request->setLaravelSession(resolve(SessionManager::class)->driver());
    $request->session()->put('locale', 'id');

    $middleware->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('id');
});

it('falls back to the application locale when nothing else is available', function (): void {
    $middleware = resolve(SetLocale::class);
    $request = Request::create('/', 'GET');
    $request->setLaravelSession(resolve(SessionManager::class)->driver());

    $middleware->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe(config('app.locale'));
});
