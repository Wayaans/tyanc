<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Rbac;

use Spatie\LaravelData\Data;

final class EffectiveAccessData extends Data
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $direct_permissions
     * @param  list<string>  $permissions
     * @param  list<array{id: string, key: string, label: string, subtitle: string, route_prefix: string, icon: string, permission_namespace: string, enabled: bool, sort_order: int, is_system: bool, href: string}>  $accessible_apps
     * @param  list<array{app_key: string, app_label: string, page_key: string, page_label: string, permission_name: string|null}>  $accessible_pages
     */
    public function __construct(
        public ?int $role_id,
        public ?string $role_name,
        public array $roles,
        public array $direct_permissions,
        public array $permissions,
        public array $accessible_apps,
        public array $accessible_pages,
    ) {}
}
