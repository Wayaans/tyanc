<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Bootstrap;

use App\Actions\Tyanc\Apps\SyncAppPages;
use App\Models\App;
use App\Models\AppPage;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Collection;

final readonly class ResolveBootstrapStatus
{
    public function __construct(
        private SyncConfiguredApps $configuredApps,
        private SyncAppPages $syncAppPages,
    ) {}

    /**
     * @return array{ready: bool, missing: list<string>, warnings: list<string>}
     */
    public function handle(): array
    {
        $missing = [];
        $warnings = [];

        foreach ($this->configuredApps->systemAppKeys() as $key) {
            $this->inspectSystemApp($key, $missing, $warnings);
        }

        $missing = [...$missing, ...$this->missingReservedRoles()];

        foreach ($this->missingPermissions() as $permissionKey) {
            $missing[] = $permissionKey;
        }

        sort($missing);
        sort($warnings);

        return [
            'ready' => $missing === [],
            'missing' => array_values(array_unique($missing)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array{ready: bool, missing: list<string>, warnings: list<string>}|null  $status
     * @return list<string>
     */
    public function registryIssues(?array $status = null): array
    {
        $resolvedStatus = $status ?? $this->handle();

        /** @var list<string> $registryIssues */
        $registryIssues = collect($resolvedStatus['missing'])
            ->filter(fn (string $item): bool => str_starts_with($item, 'apps.') || str_starts_with($item, 'app_pages.'))
            ->values()
            ->all();

        return $registryIssues;
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $warnings
     */
    private function inspectSystemApp(string $key, array &$missing, array &$warnings): void
    {
        $app = App::query()
            ->with('pages')
            ->where('key', $key)
            ->first();

        if (! $app instanceof App) {
            $missing[] = sprintf('apps.%s', $key);

            return;
        }

        if (! $this->configuredApps->isManagedIdentity($app, $key)) {
            $warnings[] = sprintf('apps.%s.customized_identity', $key);

            return;
        }

        $expectedPages = $this->syncAppPages->defaultsFor($app);

        if ($expectedPages === []) {
            return;
        }

        $existingPages = $app->pages->keyBy('key');

        Collection::make($expectedPages)
            ->each(function (array $page) use ($key, $existingPages, &$missing, &$warnings): void {
                $existingPage = $existingPages->get($page['key']);

                if (! $existingPage instanceof AppPage) {
                    $missing[] = sprintf('app_pages.%s.%s', $key, $page['key']);

                    return;
                }

                if (! $this->pageMatchesExpected($existingPage, $page)) {
                    $warnings[] = sprintf('app_pages.%s.%s.out_of_sync', $key, $page['key']);
                }
            });
    }

    /**
     * @return list<string>
     */
    private function missingReservedRoles(): array
    {
        $roles = [
            'super_admin' => (string) config('tyanc.reserved_roles.super_admin'),
            'admin' => (string) config('tyanc.reserved_roles.admin'),
        ];

        /** @var list<string> $missingRoles */
        $missingRoles = collect($roles)
            ->reject(fn (string $name): bool => Role::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists())
            ->keys()
            ->map(fn (string $key): string => sprintf('roles.%s', $key))
            ->values()
            ->all();

        return $missingRoles;
    }

    /**
     * @return list<string>
     */
    private function missingPermissions(): array
    {
        $permissionNames = PermissionKey::all();

        if ($permissionNames === []) {
            return [];
        }

        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->all();

        return array_values(array_map(
            fn (string $permissionName): string => sprintf('permissions.%s', $permissionName),
            array_diff($permissionNames, $existingPermissions),
        ));
    }

    /**
     * @param  array{key: string, label: string, route_name?: string|null, path?: string|null, permission_name?: string|null, sort_order: int, enabled: bool, is_navigation: bool, is_system: bool}  $page
     */
    private function pageMatchesExpected(AppPage $existingPage, array $page): bool
    {
        return $existingPage->label === $page['label']
            && $existingPage->route_name === ($page['route_name'] ?? null)
            && $existingPage->path === ($page['path'] ?? null)
            && $existingPage->permission_name === ($page['permission_name'] ?? null)
            && $existingPage->sort_order === $page['sort_order']
            && $existingPage->enabled === $page['enabled']
            && $existingPage->is_navigation === $page['is_navigation']
            && $existingPage->is_system === $page['is_system'];
    }
}
