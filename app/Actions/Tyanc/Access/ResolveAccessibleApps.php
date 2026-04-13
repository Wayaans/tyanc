<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Access;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Bootstrap\ResolveBootstrapStatus;
use App\Data\Navigation\AccessibleAppData;
use App\Models\App;
use App\Models\AppPage;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

final readonly class ResolveAccessibleApps
{
    public function __construct(
        private ResolveBootstrapStatus $bootstrapStatus,
        private PermissionResourceAccess $permissionAccess,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(?User $user): array
    {
        $status = $this->bootstrapStatus->handle();

        if ($this->bootstrapStatus->registryIssues($status) !== []) {
            return [];
        }

        $registeredApps = App::query()
            ->with('pages')
            ->ordered()
            ->get();

        if ($registeredApps->isEmpty()) {
            return [];
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

        $enabledPages = $app->pages
            ->where('enabled', true)
            ->values();

        if ($enabledPages->isEmpty()) {
            return false;
        }

        if (! $user instanceof User) {
            return $enabledPages->contains(fn (AppPage $page): bool => ! $this->pageRequiresPermission($page)
                && $this->pageAllowsGuestAccess($page));
        }

        return $enabledPages->contains(fn (AppPage $page): bool => ! $this->pageRequiresPermission($page)
            || $this->permissionAccess->handle($user, (string) $page->permission_name));
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

        $preferredPage = $pages->first(
            fn (AppPage $page): bool => ! $this->pageRequiresPermission($page)
                || $this->permissionAccess->handle($user, (string) $page->permission_name),
        );

        return $preferredPage instanceof AppPage ? $preferredPage : null;
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
}
