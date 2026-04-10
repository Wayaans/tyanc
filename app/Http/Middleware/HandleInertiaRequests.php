<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ResolveSidebarNavigation;
use App\Actions\ResolveTranslations;
use App\Actions\Settings\ResolveRuntimeSettings;
use App\Actions\Tyanc\Access\ResolveAccessibleApps;
use App\Data\Auth\UserData;
use App\Data\Notifications\NotificationData;
use App\Models\App;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly ResolveRuntimeSettings $runtimeSettings,
        private readonly ResolveTranslations $translations,
        private readonly ResolveAccessibleApps $accessibleApps,
    ) {}

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $defaultApp = (string) config('tyanc.default_app', 'tyanc');
        $routeName = $request->route()?->getName() ?? '';
        $user = $request->user();
        $authenticatedUser = $user instanceof User ? $user->loadMissing('profile', 'preference') : null;
        $runtimeSettings = $this->resolveRuntimeSettings($request, $authenticatedUser);
        $accessibleApps = $this->accessibleApps->handle($authenticatedUser);
        $currentApp = $this->resolveCurrentApp($request, $routeName, $accessibleApps, $defaultApp);

        $locale = $this->resolveLocale($request, $runtimeSettings);

        app()->setLocale($locale);
        $request->setLocale($locale);

        return [
            ...parent::share($request),
            'name' => $runtimeSettings['brand']['app_name'],
            'brand' => $runtimeSettings['brand'],
            'theme' => $runtimeSettings['theme'],
            'locale' => $locale,
            'availableLocales' => $this->availableLocales(),
            'translations' => $this->translations->handle($routeName, $locale),
            'userPreferences' => $runtimeSettings['preferences'],
            'auth' => [
                'user' => $authenticatedUser ? UserData::fromModel($authenticatedUser) : null,
            ],
            'notifications' => $this->notifications($authenticatedUser),
            'accessibleApps' => $accessibleApps,
            'currentApp' => $currentApp,
            'sidebarNavigation' => resolve(ResolveSidebarNavigation::class)->handle($currentApp, $authenticatedUser, $accessibleApps),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRuntimeSettings(Request $request, ?User $user): array
    {
        /** @var array<string, mixed>|null $runtimeSettings */
        $runtimeSettings = $request->attributes->get('tyanc.runtime_settings');

        if (is_array($runtimeSettings)) {
            return $runtimeSettings;
        }

        $runtimeSettings = $this->runtimeSettings->handle($user, $request);
        $request->attributes->set('tyanc.runtime_settings', $runtimeSettings);

        return $runtimeSettings;
    }

    /**
     * @param  array<string, mixed>  $runtimeSettings
     */
    private function resolveLocale(Request $request, array $runtimeSettings): string
    {
        $supportedLocales = $this->availableLocales();
        $preferenceLocale = $runtimeSettings['preferences']->resolved_locale ?? null;
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;

        if ($request->user() !== null && is_string($preferenceLocale) && in_array($preferenceLocale, $supportedLocales, true)) {
            return $preferenceLocale;
        }

        if (is_string($sessionLocale) && in_array($sessionLocale, $supportedLocales, true)) {
            return $sessionLocale;
        }

        $requestLocale = $request->getLocale();

        if (in_array($requestLocale, $supportedLocales, true)) {
            return $requestLocale;
        }

        return (string) config('app.locale', 'en');
    }

    /**
     * @return list<string>
     */
    private function availableLocales(): array
    {
        return array_values(array_keys((array) config('tyanc.supported_locales', [])));
    }

    /**
     * @return array{unread_count: int, recent: list<NotificationData>}
     */
    private function notifications(?User $user): array
    {
        if (! $user instanceof User) {
            return [
                'unread_count' => 0,
                'recent' => [],
            ];
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $notification): NotificationData => NotificationData::fromModel($notification))
                ->all(),
        ];
    }

    /**
     * @param  list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>  $accessibleApps
     */
    private function resolveCurrentApp(Request $request, string $routeName, array $accessibleApps, string $defaultApp): string
    {
        $routeScopedApp = $this->resolveRouteScopedApp($request, $routeName, $defaultApp);

        if ($routeScopedApp !== null) {
            return $routeScopedApp;
        }

        $availableApps = collect($accessibleApps)->pluck('key')->filter()->values()->all();
        $currentApp = $request->cookie('current_app');

        if (is_string($currentApp) && in_array($currentApp, $availableApps, true)) {
            return $currentApp;
        }

        if (in_array($defaultApp, $availableApps, true)) {
            return $defaultApp;
        }

        return $availableApps[0] ?? $defaultApp;
    }

    private function resolveRouteScopedApp(Request $request, string $routeName, string $defaultApp): ?string
    {
        if ($routeName === 'dashboard' || str_starts_with($routeName, 'tyanc.')) {
            return $defaultApp;
        }

        if (str_starts_with($routeName, 'demo.')) {
            return 'demo';
        }

        $firstSegment = $request->segment(1);

        if (! is_string($firstSegment) || $firstSegment === '') {
            return null;
        }

        $registeredApp = App::query()
            ->where('route_prefix', $firstSegment)
            ->value('key');

        if (is_string($registeredApp) && $registeredApp !== '') {
            return $registeredApp;
        }

        return match ($firstSegment) {
            mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/') => $defaultApp,
            mb_trim((string) config('tyanc.demo_path', 'demo'), '/') => 'demo',
            default => null,
        };
    }
}
