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
        ->and($response->getContent())->toBe('OK');
});

it('prefers persisted user preferences over the appearance cookie', function (): void {
    $user = User::factory()->create([
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
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
        ->and(View::shared('appLocale'))->toBe('id')
        ->and(View::shared('themeCssVariables')['--sidebar-variant'])->toBe('floating')
        ->and(View::shared('themeCssVariables')['--spacing-density'])->toBe('1.25');
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
