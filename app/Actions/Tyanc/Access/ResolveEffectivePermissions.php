<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Access;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Data\Navigation\AccessibleAppData;
use App\Data\Tyanc\Rbac\EffectiveAccessData;
use App\Models\App;
use App\Models\AppPage;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

final readonly class ResolveEffectivePermissions
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     */
    public function handle(array $roleNames = [], array $directPermissionNames = []): EffectiveAccessData
    {
        $roles = Role::query()
            ->with('permissions')
            ->whereIn('name', $roleNames)
            ->get();

        $directPermissions = Permission::query()
            ->whereIn('name', $directPermissionNames)
            ->get();

        $permissionNames = $roles
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->merge($directPermissions->pluck('name'))
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->sort()
            ->values();

        $apps = App::query()
            ->with('pages')
            ->enabled()
            ->ordered()
            ->get();

        $accessibleApps = $apps
            ->filter(function (App $app) use ($permissionNames): bool {
                if ($permissionNames->isEmpty()) {
                    return false;
                }

                $namespacePrefix = sprintf('%s.', $app->permission_namespace);

                if ($permissionNames->contains(fn (string $permission): bool => str_starts_with($permission, $namespacePrefix))) {
                    return true;
                }

                $access = resolve(PermissionResourceAccess::class);

                return $app->pages->contains(
                    fn ($page): bool => is_string($page->permission_name)
                        && $page->permission_name !== ''
                        && $access->matchesGrantedPermissions($permissionNames, $page->permission_name),
                );
            })
            ->map(fn (App $app): array => AccessibleAppData::fromModel($app, $this->preferredPage($app, $permissionNames))->toArray())
            ->values()
            ->all();

        $accessiblePages = $apps
            ->flatMap(fn (App $app): Collection => $app->pages
                ->filter(fn ($page): bool => $page->enabled)
                ->filter(function ($page) use ($app, $permissionNames): bool {
                    if (is_string($page->permission_name) && $page->permission_name !== '') {
                        return resolve(PermissionResourceAccess::class)->matchesGrantedPermissions($permissionNames, $page->permission_name);
                    }

                    return $permissionNames->contains(
                        fn (string $permission): bool => str_starts_with($permission, sprintf('%s.', $app->permission_namespace)),
                    );
                })
                ->map(fn ($page): array => [
                    'app_key' => $app->key,
                    'app_label' => $app->label,
                    'page_key' => $page->key,
                    'page_label' => $page->label,
                    'permission_name' => $page->permission_name,
                ]))
            ->values()
            ->all();

        return new EffectiveAccessData(
            role_id: $roles->count() === 1 ? (int) $roles->first()?->id : null,
            role_name: $roles->count() === 1 ? $roles->first()?->name : null,
            roles: $roles->pluck('name')->sort()->values()->all(),
            direct_permissions: $directPermissions->pluck('name')->sort()->values()->all(),
            permissions: $permissionNames->all(),
            accessible_apps: $accessibleApps,
            accessible_pages: $accessiblePages,
        );
    }

    private function preferredPage(App $app, Collection $permissionNames): ?AppPage
    {
        $app->loadMissing('pages');

        $pages = $app->pages
            ->where('enabled', true)
            ->sortBy(['sort_order', 'label'])
            ->values();

        $access = resolve(PermissionResourceAccess::class);

        $preferredPage = $pages->first(
            fn (AppPage $page): bool => ! is_string($page->permission_name)
                || $page->permission_name === ''
                || $access->matchesGrantedPermissions($permissionNames, $page->permission_name),
        );

        return $preferredPage instanceof AppPage ? $preferredPage : $pages->first();
    }
}
