<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Apps\EnsureAppRegistrySeeded;
use App\Models\App;
use App\Models\AppPage;
use App\Models\User;
use Closure;
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

        $routeName = $request->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'tyanc.notifications.')) {
            return $next($request);
        }

        resolve(EnsureAppRegistrySeeded::class)->handle();

        $registeredApp = $this->resolveAppByPrefix($request);

        if (! $registeredApp instanceof App) {
            return $next($request);
        }

        abort_if(! $registeredApp->enabled, 404);

        $routePermission = $this->routePermission($routeName);

        if ($routePermission !== null) {
            abort_unless(resolve(PermissionResourceAccess::class)->handle($user, $routePermission), 403);

            return $next($request);
        }

        $page = $this->resolvePage($request, $registeredApp);

        abort_if(! $page instanceof AppPage, 403);

        $page->loadMissing('app');

        abort_if(! $page->enabled || ! $page->app?->enabled, 404);

        if (! is_string($page->permission_name) || mb_trim($page->permission_name) === '') {
            return $next($request);
        }

        abort_unless(resolve(PermissionResourceAccess::class)->handle($user, $page->permission_name), 403);

        return $next($request);
    }

    private function routePermission(string $routeName): ?string
    {
        return match (true) {
            str_starts_with($routeName, 'tyanc.users.import.') => 'tyanc.users.import',
            str_starts_with($routeName, 'tyanc.users.export.') || $routeName === 'tyanc.users.export' => 'tyanc.users.export',
            str_starts_with($routeName, 'tyanc.activity-log.export.') || $routeName === 'tyanc.activity-log.export' => 'tyanc.activity_log.export',
            $routeName === 'tyanc.users.approvals.approve' => 'tyanc.approvals.approve',
            $routeName === 'tyanc.users.approvals.reject' => 'tyanc.approvals.reject',
            default => null,
        };
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

    private function resolvePage(Request $request, App $app): ?AppPage
    {
        $routeName = $request->route()?->getName();
        $path = '/'.mb_trim($request->path(), '/');

        if ((! is_string($routeName) || $routeName === '') && $path === '/') {
            return null;
        }

        $app->loadMissing('pages');

        $pages = $app->pages->values();

        $exactPage = $pages->first(fn (AppPage $page): bool => (is_string($routeName) && $routeName !== '' && $page->route_name === $routeName)
            || ($path !== '/' && $page->path === $path));

        if ($exactPage instanceof AppPage) {
            return $exactPage;
        }

        $matchingPage = $pages
            ->filter(fn (AppPage $page): bool => $this->matchesRouteScope($routeName, $page)
                || $this->matchesPathScope($path, $page))
            ->sortByDesc(fn (AppPage $page): int => max(
                is_string($page->route_name) ? mb_strlen($page->route_name) : 0,
                is_string($page->path) ? mb_strlen($page->path) : 0,
            ))
            ->first();

        return $matchingPage instanceof AppPage ? $matchingPage : null;
    }

    private function matchesRouteScope(?string $routeName, AppPage $page): bool
    {
        if (! is_string($routeName) || $routeName === '' || ! is_string($page->route_name) || $page->route_name === '') {
            return false;
        }

        if ($page->route_name === $routeName) {
            return true;
        }

        if (str_ends_with($page->route_name, '.index')) {
            $routePrefix = mb_substr($page->route_name, 0, -mb_strlen('.index'));

            return $routeName === $routePrefix || str_starts_with($routeName, sprintf('%s.', $routePrefix));
        }

        return str_starts_with($routeName, sprintf('%s.', $page->route_name));
    }

    private function matchesPathScope(string $path, AppPage $page): bool
    {
        if ($path === '/' || ! is_string($page->path) || $page->path === '') {
            return false;
        }

        $pagePath = '/'.mb_trim($page->path, '/');

        return $pagePath === $path || str_starts_with($path, sprintf('%s/', $pagePath));
    }
}
