<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Access;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Navigation\AccessibleAppData;
use App\Models\App;
use App\Models\AppPage;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\AppRegistrySeeder;
use Illuminate\Support\Collection;

final readonly class ResolveAccessibleApps
{
    /**
     * @return list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>
     */
    public function handle(?User $user): array
    {
        if (App::query()->doesntExist() || AppPage::query()->doesntExist()) {
            resolve(AppRegistrySeeder::class)->run();
        }

        $registeredApps = App::query()
            ->with('pages')
            ->enabled()
            ->ordered()
            ->get();

        if ($registeredApps->isEmpty()) {
            return $this->fallbackAccessibleApps();
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

        if (! $user instanceof User) {
            return true;
        }

        $app->loadMissing('pages');

        $enabledPages = $app->pages->where('enabled', true);
        $protectedPages = $enabledPages
            ->filter(fn ($page): bool => is_string($page->permission_name) && $page->permission_name !== '');
        $openPages = $enabledPages
            ->reject(fn ($page): bool => is_string($page->permission_name) && $page->permission_name !== '');

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
            return $pages->first();
        }

        $access = resolve(PermissionResourceAccess::class);

        $preferredPage = $pages->first(
            fn (AppPage $page): bool => ! is_string($page->permission_name)
                || $page->permission_name === ''
                || $access->handle($user, $page->permission_name),
        );

        return $preferredPage instanceof AppPage ? $preferredPage : $pages->first();
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
