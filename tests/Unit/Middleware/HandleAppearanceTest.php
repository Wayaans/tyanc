<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

it('shares resolved appearance and css variables from the cookie with views', function (): void {
    $middleware = resolve(HandleAppearance::class);

    $request = Request::create('/', 'GET');
    $request->cookies->set('appearance', 'dark');

    $response = $middleware->handle($request, fn ($req): Response => response('OK'));

    expect(View::shared('appearance'))->toBe('dark')
        ->and(View::shared('themeCssVariables'))->toBeArray()
        ->and(View::shared('themeCssVariables'))->not->toHaveKey('--sidebar-accent')
        ->and(View::shared('themeCssVariables'))->not->toHaveKey('--accent')
        ->and(View::shared('themeCssVariables'))->not->toHaveKey('--secondary')
        ->and(View::shared('themeCssVariables')['--spacing-density'])->toBe('1')
        ->and(View::shared('appLocale'))->toBe(config('app.locale'))
        ->and(View::shared('appTimezone'))->toBe(config('app.timezone'))
        ->and($response->getContent())->toBe('OK');
});

it('prefers persisted display preferences while keeping locale and timezone from the user profile', function (): void {
    $originalTimezone = date_default_timezone_get();
    $originalAppTimezone = config('app.timezone');

    $user = User::factory()->create([
        'locale' => 'en',
        'timezone' => 'Asia/Makassar',
    ]);

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
        'timezone' => 'UTC',
        'appearance' => 'dark',
        'sidebar_variant' => 'floating',
        'spacing_density' => 'comfortable',
    ]);

    $middleware = resolve(HandleAppearance::class);

    $request = Request::create('/', 'GET');
    $request->cookies->set('appearance', 'light');
    $request->setUserResolver(fn (): User => $user);

    $middleware->handle($request, fn ($req): Response => response('OK'));

    expect(View::shared('appearance'))->toBe('dark')
        ->and(View::shared('appLocale'))->toBe('en')
        ->and(View::shared('appTimezone'))->toBe('Asia/Makassar')
        ->and(config('app.timezone'))->toBe('Asia/Makassar')
        ->and(date_default_timezone_get())->toBe('Asia/Makassar')
        ->and(View::shared('themeCssVariables')['--sidebar-variant'])->toBe('floating')
        ->and(View::shared('themeCssVariables')['--spacing-density'])->toBe('1.25');

    config(['app.timezone' => $originalAppTimezone]);
    date_default_timezone_set($originalTimezone);
});

it('shares the resolved brand metadata with views', function (): void {
    $middleware = resolve(HandleAppearance::class);

    $request = Request::create('/', 'GET');

    $middleware->handle($request, fn ($req): Response => response('OK'));

    expect(View::shared('brand'))->toMatchArray([
        'app_name' => 'Tyanc',
        'company_legal_name' => 'Tyanc',
    ]);
});
