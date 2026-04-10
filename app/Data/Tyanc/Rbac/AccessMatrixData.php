<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Rbac;

use Spatie\LaravelData\Data;

final class AccessMatrixData extends Data
{
    /**
     * @param  array<string, mixed>  $matrix
     * @param  list<RoleData>  $roles
     * @param  list<PermissionData>  $permissions
     * @param  list<array<string, mixed>>  $apps
     */
    public function __construct(
        public array $matrix,
        public array $roles,
        public array $permissions,
        public array $apps,
        public ?int $selected_role_id,
        public ?string $selected_app_key,
        public ?EffectiveAccessData $effective_preview,
    ) {}
}
