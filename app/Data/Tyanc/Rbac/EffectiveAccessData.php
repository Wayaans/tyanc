<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Rbac;

use Spatie\LaravelData\Data;

final class EffectiveAccessData extends Data
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $direct_permissions
     * @param  array<int, string>  $permissions
     * @param  array<int, array<string, mixed>>  $accessible_apps
     * @param  array<int, array{app_key: string, app_label: string, page_key: string, page_label: string, permission_name: string|null}>  $accessible_pages
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
