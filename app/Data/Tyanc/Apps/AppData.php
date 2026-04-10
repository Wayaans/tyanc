<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Apps;

use App\Data\Navigation\AccessibleAppData;
use App\Models\App;
use App\Models\AppPage;
use Spatie\LaravelData\Data;

final class AppData extends Data
{
    /**
     * @param  list<AppPageData>  $pages
     */
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
        public array $pages,
    ) {}

    public static function fromModel(App $app): self
    {
        $app->loadMissing('pages');
        $accessible = AccessibleAppData::fromModel($app);

        return new self(
            id: (string) $app->id,
            key: $app->key,
            label: $app->label,
            subtitle: $accessible->subtitle,
            route_prefix: $app->route_prefix,
            icon: $app->icon,
            permission_namespace: $app->permission_namespace,
            enabled: $app->enabled,
            sort_order: $app->sort_order,
            is_system: $app->is_system,
            href: $accessible->href,
            pages: $app->pages
                ->sortBy(['sort_order', 'label'])
                ->map(fn (AppPage $page): AppPageData => AppPageData::fromModel($page))
                ->values()
                ->all(),
        );
    }
}
