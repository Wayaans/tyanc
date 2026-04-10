<?php

declare(strict_types=1);

namespace App\Data\Navigation;

use App\Models\App;
use App\Models\AppPage;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\Data;

final class AccessibleAppData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public string $subtitle,
        public string $route_prefix,
        public string $icon,
        public string $permission_namespace,
        public bool $enabled,
        public int $sort_order,
        public bool $is_system,
        public string $href,
    ) {}

    public static function fromModel(App $app, ?AppPage $preferredPage = null): self
    {
        return new self(
            id: (string) $app->id,
            key: $app->key,
            label: $app->label,
            subtitle: self::resolveSubtitle($app->key),
            route_prefix: $app->route_prefix,
            icon: $app->icon,
            permission_namespace: $app->permission_namespace,
            enabled: $app->enabled,
            sort_order: $app->sort_order,
            is_system: $app->is_system,
            href: self::resolveHref($app, $preferredPage),
        );
    }

    /**
     * @param  array{title?: mixed, subtitle?: mixed, icon?: mixed, route?: mixed}  $config
     */
    public static function fromConfig(string $key, array $config, int $sortOrder = 0): self
    {
        $routePrefix = match ($key) {
            'tyanc' => mb_trim((string) config('tyanc.admin_path', 'tyanc'), '/'),
            'demo' => mb_trim((string) config('tyanc.demo_path', 'demo'), '/'),
            default => $key,
        };

        $routeName = is_string($config['route'] ?? null) ? $config['route'] : null;

        return new self(
            id: $key,
            key: $key,
            label: (string) ($config['title'] ?? $key),
            subtitle: (string) ($config['subtitle'] ?? ''),
            route_prefix: $routePrefix,
            icon: (string) ($config['icon'] ?? 'layout-grid'),
            permission_namespace: $key,
            enabled: true,
            sort_order: $sortOrder,
            is_system: in_array($key, ['tyanc', 'demo'], true),
            href: is_string($routeName) && Route::has($routeName)
                ? route($routeName, absolute: false)
                : '/'.mb_trim($routePrefix, '/'),
        );
    }

    public static function resolveHref(App $app, ?AppPage $preferredPage = null): string
    {
        $app->loadMissing('pages');

        if ($preferredPage instanceof AppPage && $preferredPage->enabled) {
            return self::resolvePageHref($preferredPage);
        }

        $page = $app->pages
            ->where('enabled', true)
            ->sortBy(['sort_order', 'label'])
            ->first();

        if ($page instanceof AppPage) {
            return self::resolvePageHref($page);
        }

        $configuredRoute = config(sprintf('sidebar-menu.apps.%s.route', $app->key));

        if (is_string($configuredRoute) && Route::has($configuredRoute)) {
            return route($configuredRoute, absolute: false);
        }

        return '/'.mb_trim($app->route_prefix, '/');
    }

    public static function resolvePageHref(AppPage $page): string
    {
        if (is_string($page->route_name) && $page->route_name !== '' && Route::has($page->route_name)) {
            return route($page->route_name, absolute: false);
        }

        if (is_string($page->path) && $page->path !== '') {
            return str_starts_with($page->path, '/') ? $page->path : '/'.$page->path;
        }

        return '#';
    }

    private static function resolveSubtitle(string $key): string
    {
        $subtitle = config(sprintf('sidebar-menu.apps.%s.subtitle', $key));

        return is_string($subtitle) ? $subtitle : '';
    }
}
