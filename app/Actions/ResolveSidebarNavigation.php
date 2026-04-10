<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\User;

final readonly class ResolveSidebarNavigation
{
    /**
     * @param  list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>  $accessibleApps
     * @return array{
     *     apps: list<array{id: string, title: string, subtitle: string, icon: string, href: string}>,
     *     menu: list<array{title: string, icon?: string, href?: string, permission?: string|null, children?: list<array{title: string, icon?: string, href?: string, permission?: string|null, children?: array}>}>
     * }
     */
    public function handle(string $currentApp, ?User $user = null, array $accessibleApps = []): array
    {
        /** @var array<string, array<string, mixed>> $configuredApps */
        $configuredApps = config('sidebar-menu.apps', []);

        if (! array_key_exists($currentApp, $configuredApps)) {
            $currentApp = 'tyanc';
        }

        return [
            'apps' => $accessibleApps === []
                ? $this->resolveConfiguredApps($configuredApps)
                : $this->resolveAccessibleApps($accessibleApps),
            'menu' => $this->resolveMenu($configuredApps[$currentApp]['menu'] ?? [], $user),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $apps
     * @return list<array{id: string, title: string, subtitle: string, icon: string, href: string}>
     */
    private function resolveConfiguredApps(array $apps): array
    {
        $resolvedApps = [];

        foreach ($apps as $id => $app) {
            $resolvedApps[] = [
                'id' => $id,
                'title' => __((string) ($app['title'] ?? '')),
                'subtitle' => __((string) ($app['subtitle'] ?? '')),
                'icon' => (string) ($app['icon'] ?? 'layout-grid'),
                'href' => $this->resolveHref($app),
            ];
        }

        return $resolvedApps;
    }

    /**
     * @param  list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>  $apps
     * @return list<array{id: string, title: string, subtitle: string, icon: string, href: string}>
     */
    private function resolveAccessibleApps(array $apps): array
    {
        return array_map(
            fn (array $app): array => [
                'id' => $app['key'],
                'title' => __($app['label']),
                'subtitle' => __($app['subtitle']),
                'icon' => $app['icon'],
                'href' => $app['href'],
            ],
            $apps,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{title: string, icon?: string, href?: string, permission?: string|null, children?: array}>
     */
    private function resolveMenu(array $items, ?User $user): array
    {
        $resolvedItems = [];

        foreach ($items as $item) {
            $permission = is_string($item['permission'] ?? null) ? $item['permission'] : null;
            $children = isset($item['children']) && is_array($item['children'])
                ? $this->resolveMenu($item['children'], $user)
                : null;
            $hasPermission = $permission === null || $this->canAccessPermission($user, $permission);

            if (! $hasPermission && ($children === null || $children === [])) {
                continue;
            }

            $resolvedItem = [
                'title' => __((string) ($item['title'] ?? '')),
            ];

            if (isset($item['icon'])) {
                $resolvedItem['icon'] = (string) $item['icon'];
            }

            if (array_key_exists('permission', $item)) {
                $resolvedItem['permission'] = $permission;
            }

            if ($hasPermission && (isset($item['route']) || isset($item['href']))) {
                $resolvedItem['href'] = $this->resolveHref($item);
            }

            if ($children !== null && $children !== []) {
                $resolvedItem['children'] = $children;
            }

            if (! array_key_exists('href', $resolvedItem) && ! array_key_exists('children', $resolvedItem)) {
                continue;
            }

            $resolvedItems[] = $resolvedItem;
        }

        return $resolvedItems;
    }

    private function canAccessPermission(?User $user, string $permission): bool
    {
        if (! $user instanceof User) {
            return true;
        }

        return resolve(PermissionResourceAccess::class)->handle($user, $permission);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveHref(array $item): string
    {
        if (isset($item['route'])) {
            return route((string) $item['route'], absolute: false);
        }

        return (string) ($item['href'] ?? '#');
    }
}
