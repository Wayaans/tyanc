<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ResolveSidebarNavigation;
use App\Actions\Settings\ResolveRuntimeSettings;
use App\Data\Auth\UserData;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly ResolveRuntimeSettings $runtimeSettings) {}

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
        $availableApps = array_keys(config('sidebar-menu.apps', []));
        $routeName = $request->route()?->getName() ?? '';
        $currentApp = $request->cookie('current_app');
        $user = $request->user();
        $authenticatedUser = $user instanceof User ? $user->loadMissing('profile', 'preference') : null;
        $runtimeSettings = $this->resolveRuntimeSettings($request, $authenticatedUser);

        if ($routeName === 'dashboard') {
            $currentApp = $defaultApp;
        } elseif (str_starts_with($routeName, 'demo.')) {
            $currentApp = 'demo';
        } elseif (! in_array($currentApp, $availableApps, true)) {
            $currentApp = $defaultApp;
        }

        return [
            ...parent::share($request),
            'name' => $runtimeSettings['brand']['app_name'],
            'brand' => $runtimeSettings['brand'],
            'theme' => $runtimeSettings['theme'],
            'userPreferences' => $runtimeSettings['preferences'],
            'auth' => [
                'user' => $authenticatedUser ? UserData::fromModel($authenticatedUser) : null,
            ],
            'currentApp' => $currentApp,
            'sidebarNavigation' => resolve(ResolveSidebarNavigation::class)->handle($currentApp),
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
}
