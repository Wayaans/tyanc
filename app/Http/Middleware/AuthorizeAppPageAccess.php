<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Apps\SyncAppPages;
use App\Actions\Tyanc\Bootstrap\ResolveBootstrapStatus;
use App\Actions\Tyanc\Bootstrap\SyncConfiguredApps;
use App\Exceptions\TyancBootstrapIncomplete;
use App\Models\App;
use App\Models\AppPage;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizeAppPageAccess
{
    public function __construct(
        private PermissionResourceAccess $permissionAccess,
        private ResolveBootstrapStatus $bootstrapStatus,
        private SyncConfiguredApps $configuredApps,
        private SyncAppPages $syncAppPages,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $this->requiresRegistryGuard($request)) {
            return $next($request);
        }

        $status = $this->bootstrapStatus->handle();
        $firstSegment = (string) $request->segment(1);

        if ($this->isSystemPrefix($firstSegment)) {
            $registryIssues = $this->bootstrapStatus->registryIssues($status);

            if ($registryIssues !== []) {
                throw TyancBootstrapIncomplete::forMissing($registryIssues, $status['warnings']);
            }
        }

        $registeredApp = $this->resolveAppByPrefix($request);

        if (! $registeredApp instanceof App) {
            throw TyancBootstrapIncomplete::forMissing([
                $this->missingAppKeyForRequest($request),
            ], message: 'Tyanc bootstrap is incomplete for this app route.');
        }

        abort_if(! $registeredApp->enabled, 404);

        $routeName = $request->route()?->getName() ?? '';

        if (
            str_starts_with($routeName, 'tyanc.notifications.')
            || in_array($routeName, ['tyanc.approvals.index', 'tyanc.approvals.my-requests', 'cumpu.approvals.cancel', 'cumpu.approvals.show'], true)
        ) {
            return $next($request);
        }

        $routePermission = $this->routePermission($routeName);

        if ($routePermission !== null) {
            abort_unless($this->permissionAccess->handle($user, $routePermission), 403);

            return $next($request);
        }

        $page = $this->resolvePage($request, $registeredApp);

        if (! $page instanceof AppPage) {
            $expectedManagedPage = $this->resolveExpectedManagedPage($request, $registeredApp);

            if (is_array($expectedManagedPage)) {
                throw TyancBootstrapIncomplete::forMissing([
                    sprintf('app_pages.%s.%s', $registeredApp->key, $expectedManagedPage['key']),
                ]);
            }

            abort(403);
        }

        $page->loadMissing('app');

        abort_if(! $page->enabled || ! $page->app->enabled, 404);

        if (! is_string($page->permission_name) || mb_trim($page->permission_name) === '') {
            return $next($request);
        }

        abort_unless($this->permissionAccess->handle($user, $page->permission_name), 403);

        return $next($request);
    }

    private function requiresRegistryGuard(Request $request): bool
    {
        $firstSegment = $request->segment(1);

        if (! is_string($firstSegment) || $firstSegment === '') {
            return false;
        }

        return in_array($firstSegment, $this->configuredPrefixes(), true)
            || App::query()->where('route_prefix', $firstSegment)->exists();
    }

    /**
     * @return list<string>
     */
    private function configuredPrefixes(): array
    {
        /** @var list<string> $configuredPrefixes */
        $configuredPrefixes = collect($this->configuredApps->configuredAppKeys())
            ->map(fn (string $key): string => $this->configuredApps->routePrefix($key))
            ->values()
            ->all();

        return $configuredPrefixes;
    }

    private function isSystemPrefix(string $prefix): bool
    {
        return collect($this->configuredApps->systemAppKeys())
            ->map(fn (string $key): string => $this->configuredApps->routePrefix($key))
            ->contains($prefix);
    }

    private function routePermission(string $routeName): ?string
    {
        return match (true) {
            str_starts_with($routeName, 'tyanc.users.import.') => 'tyanc.users.import',
            str_starts_with($routeName, 'tyanc.users.export.') || $routeName === 'tyanc.users.export' => 'tyanc.users.export',
            str_starts_with($routeName, 'tyanc.activity-log.export.') || $routeName === 'tyanc.activity-log.export' => 'tyanc.activity_log.export',
            $routeName === 'tyanc.files.store' => 'tyanc.files.upload',
            $routeName === 'tyanc.files.show' => 'tyanc.files.viewany',
            $routeName === 'tyanc.files.download' => 'tyanc.files.download',
            $routeName === 'tyanc.files.destroy' => 'tyanc.files.delete',
            $routeName === 'cumpu.approvals.approve' => 'cumpu.approvals.approve',
            $routeName === 'cumpu.approvals.reject' => 'cumpu.approvals.reject',
            $routeName === 'cumpu.approval-rules.sync' => 'cumpu.approval_rules.manage',
            $routeName === 'cumpu.approval-rules.toggle' => 'cumpu.approval_rules.manage',
            default => null,
        };
    }

    private function resolveAppByPrefix(Request $request): ?App
    {
        $firstSegment = $request->segment(1);

        if (! is_string($firstSegment) || $firstSegment === '') {
            return null;
        }

        $app = App::query()
            ->where('route_prefix', $firstSegment)
            ->first();

        return $app instanceof App ? $app : null;
    }

    private function missingAppKeyForRequest(Request $request): string
    {
        $firstSegment = $request->segment(1);
        $configuredApp = collect($this->configuredApps->configuredAppKeys())
            ->first(fn (string $key): bool => $this->configuredApps->routePrefix($key) === $firstSegment);

        if (is_string($configuredApp) && $configuredApp !== '') {
            return sprintf('apps.%s', $configuredApp);
        }

        return sprintf('apps.route_prefix.%s', (string) $firstSegment);
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
            ->filter(fn (AppPage $page): bool => $this->matchesRouteScope($routeName, $page->route_name)
                || $this->matchesPathScope($path, $page->path))
            ->sortByDesc(fn (AppPage $page): int => max(
                is_string($page->route_name) ? mb_strlen($page->route_name) : 0,
                is_string($page->path) ? mb_strlen($page->path) : 0,
            ))
            ->first();

        return $matchingPage instanceof AppPage ? $matchingPage : null;
    }

    /**
     * @return array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}|null
     */
    private function resolveExpectedManagedPage(Request $request, App $app): ?array
    {
        if (! in_array($app->key, $this->configuredApps->configuredAppKeys(), true) || ! $this->configuredApps->isManagedIdentity($app, $app->key)) {
            return null;
        }

        $routeName = $request->route()?->getName();
        $path = '/'.mb_trim($request->path(), '/');

        return collect($this->syncAppPages->defaultsFor($app))
            ->first(fn (array $page): bool => $this->matchesRouteScope($routeName, $page['route_name'] ?? null)
                || $this->matchesPathScope($path, $page['path'] ?? null));
    }

    private function matchesRouteScope(?string $routeName, ?string $pageRouteName): bool
    {
        if (! is_string($routeName) || $routeName === '' || ! is_string($pageRouteName) || $pageRouteName === '') {
            return false;
        }

        if ($pageRouteName === $routeName) {
            return true;
        }

        if (str_ends_with($pageRouteName, '.index')) {
            $routePrefix = mb_substr($pageRouteName, 0, -mb_strlen('.index'));

            return $routeName === $routePrefix || str_starts_with($routeName, sprintf('%s.', $routePrefix));
        }

        return str_starts_with($routeName, sprintf('%s.', $pageRouteName));
    }

    private function matchesPathScope(string $path, ?string $pagePathValue): bool
    {
        if ($path === '/' || ! is_string($pagePathValue) || $pagePathValue === '') {
            return false;
        }

        $pagePath = '/'.mb_trim($pagePathValue, '/');

        return $pagePath === $path || str_starts_with($path, sprintf('%s/', $pagePath));
    }
}
