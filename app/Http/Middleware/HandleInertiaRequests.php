<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ResolveSidebarNavigation;
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
        $routeName = $request->route()?->getName() ?? '';
        $currentApp = $request->cookie('current_app');

        if ($routeName === 'dashboard') {
            $currentApp = 'tyanc';
        } elseif (str_starts_with($routeName, 'demo.')) {
            $currentApp = 'demo';
        } elseif (! in_array($currentApp, ['tyanc', 'demo'], true)) {
            $currentApp = 'tyanc';
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'currentApp' => $currentApp,
            'sidebarNavigation' => resolve(ResolveSidebarNavigation::class)->handle($currentApp),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
