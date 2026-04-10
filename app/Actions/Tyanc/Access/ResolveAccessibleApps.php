<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Access;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Apps\EnsureAppRegistrySeeded;
use App\Data\Navigation\AccessibleAppData;
use App\Models\App;
use App\Models\AppPage;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

final readonly class ResolveAccessibleApps
{
    /**
     * @return list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>
     */
    public function handle(?User $user): array
    {
        resolve(EnsureAppRegistrySeeded::class)->handle();

        $registeredApps = App::query()
            ->with('pages')
            ->ordered()
            ->get();

        if ($registeredApps->isEmpty()) {
            return $user instanceof User ? $this->fallbackAccessibleApps() : [];
        }

        return $registeredApps
            ->filter(fn (App $app): bool => $this->canAccessApp($user, $app))
            ->map(fn (App $app): array => AccessibleAppData::fromModel($app, $this->preferredPage($user, $app))->toArray())
            ->values()
            ->all();
    }

    private function canAccessApp(?User $user, App $app): bool
    {
        if (! $app->enabled) {
            return false;
        }

        $app->loadMissing('pages');

        $enabledPages = $app->pages->where('enabled', true);
        $protectedPages = $enabledPages
            ->filter(fn (AppPage $page): bool => $this->pageRequiresPermission($page));
        $openPages = $enabledPages
            ->reject(fn (AppPage $page): bool => $this->pageRequiresPermission($page));

        if (! $user instanceof User) {
            return $openPages->contains(fn (AppPage $page): bool => $this->pageAllowsGuestAccess($page));
        }

        if ($openPages->isNotEmpty()) {
            return true;
        }

        if ($protectedPages->isNotEmpty()) {
            $access = resolve(PermissionResourceAccess::class);

            return $protectedPages->contains(
                fn ($page): bool => $access->handle($user, $page->permission_name),
            );
        }

        if ($app->isSystem()) {
            return true;
        }

        $namespace = mb_trim($app->permission_namespace, '.');

        if ($namespace === '') {
            return true;
        }

        $hasNamespacedPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', sprintf('%s.%%', $namespace))
            ->exists();

        if (! $hasNamespacedPermissions) {
            return true;
        }

        if ($user->hasRole(config('tyanc.reserved_roles.super_admin'))) {
            return true;
        }

        return $user->getAllPermissions()->contains(
            fn (Permission $permission): bool => str_starts_with($permission->name, sprintf('%s.', $namespace)),
        );
    }

    private function preferredPage(?User $user, App $app): ?AppPage
    {
        $app->loadMissing('pages');

        $pages = $app->pages
            ->where('enabled', true)
            ->sortBy(['sort_order', 'label'])
            ->values();

        if (! $user instanceof User) {
            $preferredPublicPage = $pages->first(
                fn (AppPage $page): bool => ! $this->pageRequiresPermission($page)
                    && $this->pageAllowsGuestAccess($page),
            );

            return $preferredPublicPage instanceof AppPage ? $preferredPublicPage : null;
        }

        $access = resolve(PermissionResourceAccess::class);

        $preferredPage = $pages->first(
            fn (AppPage $page): bool => ! is_string($page->permission_name)
                || $page->permission_name === ''
                || $access->handle($user, $page->permission_name),
        );

        return $preferredPage instanceof AppPage ? $preferredPage : $pages->first();
    }

    private function pageRequiresPermission(AppPage $page): bool
    {
        return is_string($page->permission_name) && mb_trim($page->permission_name) !== '';
    }

    private function pageAllowsGuestAccess(AppPage $page): bool
    {
        if ($this->pageRequiresPermission($page)) {
            return false;
        }

        if (! is_string($page->route_name) || $page->route_name === '' || ! Route::has($page->route_name)) {
            return false;
        }

        $route = Route::getRoutes()->getByName($page->route_name);

        if (! $route instanceof LaravelRoute) {
            return false;
        }

        return Collection::make($route->gatherMiddleware())
            ->filter(fn (mixed $middleware): bool => is_string($middleware) && $middleware !== '')
            ->doesntContain(fn (string $middleware): bool => $middleware === 'auth'
                || str_starts_with($middleware, 'auth:')
                || $middleware === 'verified');
    }

    /**
     * @return list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>
     */
    private function fallbackAccessibleApps(): array
    {
        return Collection::make((array) config('sidebar-menu.apps', []))
            ->map(fn (array $config, string $key): array => AccessibleAppData::fromConfig($key, $config)->toArray())
            ->values()
            ->all();
    }
}
