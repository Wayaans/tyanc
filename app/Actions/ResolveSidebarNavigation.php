<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class ResolveSidebarNavigation
{
    /**
     * @return array{
     *     apps: list<array{id: string, title: string, subtitle: string, icon: string, href: string}>,
     *     menu: list<array{title: string, icon?: string, href?: string, permission?: string|null, children?: list<array{title: string, icon?: string, href?: string, permission?: string|null, children?: array}>}>
     * }
     */
    public function handle(string $currentApp): array
    {
        /** @var array<string, array<string, mixed>> $apps */
        $apps = config('sidebar-menu.apps', []);

        if (! array_key_exists($currentApp, $apps)) {
            $currentApp = 'tyanc';
        }

        return [
            'apps' => $this->resolveApps($apps),
            'menu' => $this->resolveMenu($apps[$currentApp]['menu'] ?? []),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $apps
     * @return list<array{id: string, title: string, subtitle: string, icon: string, href: string}>
     */
    private function resolveApps(array $apps): array
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
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{title: string, icon?: string, href?: string, permission?: string|null, children?: array}>
     */
    private function resolveMenu(array $items): array
    {
        $resolvedItems = [];

        foreach ($items as $item) {
            $resolvedItem = [
                'title' => __((string) ($item['title'] ?? '')),
            ];

            if (isset($item['icon'])) {
                $resolvedItem['icon'] = (string) $item['icon'];
            }

            if (array_key_exists('permission', $item)) {
                $resolvedItem['permission'] = is_string($item['permission'])
                    ? $item['permission']
                    : null;
            }

            if (isset($item['route']) || isset($item['href'])) {
                $resolvedItem['href'] = $this->resolveHref($item);
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $resolvedItem['children'] = $this->resolveMenu($item['children']);
            }

            $resolvedItems[] = $resolvedItem;
        }

        return $resolvedItems;
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
