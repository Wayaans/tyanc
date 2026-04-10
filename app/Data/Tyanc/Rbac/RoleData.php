<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Rbac;

use App\Models\Role;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class RoleData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $guard_name,
        public int $level,
        public bool $is_reserved,
        public bool $is_delete_protected,
        public int $permission_count,
        public int $user_count,
        public array $permissions,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Role $role): self
    {
        $role->loadMissing('permissions');

        return new self(
            id: (int) $role->id,
            name: $role->name,
            guard_name: $role->guard_name,
            level: (int) $role->level,
            is_reserved: in_array($role->name, (array) config('tyanc.immutable_roles', []), true),
            is_delete_protected: in_array($role->name, (array) config('tyanc.undeletable_roles', []), true),
            permission_count: $role->permissions_count ?? $role->permissions->count(),
            user_count: $role->users_count ?? $role->users()->count(),
            permissions: $role->permissions->pluck('name')->sort()->values()->all(),
            created_at: $role->created_at instanceof CarbonInterface ? $role->created_at->toIso8601String() : now()->toIso8601String(),
            updated_at: $role->updated_at instanceof CarbonInterface ? $role->updated_at->toIso8601String() : now()->toIso8601String(),
        );
    }
}
