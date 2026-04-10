<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\App;
use App\Models\AppPage;
use App\Models\User;
use Closure;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeAppPageAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (App::query()->doesntExist() || AppPage::query()->doesntExist()) {
            resolve(AppRegistrySeeder::class)->run();
        }

        $page = $this->resolvePage($request);

        if (! $page instanceof AppPage) {
            $registeredApp = $this->resolveAppByPrefix($request);

            abort_if($registeredApp instanceof App && ! $registeredApp->enabled, 404);

            return $next($request);
        }

        $page->loadMissing('app');

        abort_if(! $page->enabled || ! $page->app?->enabled, 404);

        if (! is_string($page->permission_name) || mb_trim($page->permission_name) === '') {
            return $next($request);
        }

        abort_unless(resolve(PermissionResourceAccess::class)->handle($user, $page->permission_name), 403);

        return $next($request);
    }

    private function resolveAppByPrefix(Request $request): ?App
    {
        $firstSegment = $request->segment(1);

        if (! is_string($firstSegment) || $firstSegment === '') {
            return null;
        }

        return App::query()
            ->where('route_prefix', $firstSegment)
            ->first();
    }

    private function resolvePage(Request $request): ?AppPage
    {
        $routeName = $request->route()?->getName();
        $path = '/'.mb_trim($request->path(), '/');

        if ((! is_string($routeName) || $routeName === '') && $path === '/') {
            return null;
        }

        return AppPage::query()
            ->with('app')
            ->where(function (Builder $query) use ($routeName, $path): void {
                if (is_string($routeName) && $routeName !== '' && $path !== '/') {
                    $query->where('route_name', $routeName)
                        ->orWhere('path', $path);

                    return;
                }

                if (is_string($routeName) && $routeName !== '') {
                    $query->where('route_name', $routeName);

                    return;
                }

                if ($path !== '/') {
                    $query->where('path', $path);
                }
            })
            ->orderByRaw(
                'case when route_name = ? then 0 when path = ? then 1 else 2 end',
                [is_string($routeName) ? $routeName : '', $path],
            )
            ->first();
    }
}
