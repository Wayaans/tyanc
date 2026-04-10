<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Apps;

use App\Models\AppPage;
use Spatie\LaravelData\Data;

final class AppPageData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public ?string $route_name,
        public ?string $path,
        public ?string $permission_name,
        public int $sort_order,
        public bool $enabled,
        public bool $is_navigation,
        public bool $is_system,
    ) {}

    public static function fromModel(AppPage $page): self
    {
        return new self(
            id: (string) $page->id,
            key: $page->key,
            label: $page->label,
            route_name: $page->route_name,
            path: $page->path,
            permission_name: $page->permission_name,
            sort_order: $page->sort_order,
            enabled: $page->enabled,
            is_navigation: $page->is_navigation,
            is_system: $page->is_system,
        );
    }
}
