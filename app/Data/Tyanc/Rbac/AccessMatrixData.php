<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Rbac;

use App\Data\Tyanc\Apps\AppData;
use Spatie\LaravelData\Data;

final class AccessMatrixData extends Data
{
    /**
     * @param  array<string, mixed>  $matrix
     * @param  array<int, RoleData>  $roles
     * @param  array<int, PermissionData>  $permissions
     * @param  array<int, AppData>  $apps
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
