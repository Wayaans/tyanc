<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Rbac;

use App\Models\Permission;
use App\Support\Permissions\PermissionKey;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class PermissionData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public string $guard_name,
        public string $app,
        public string $app_label,
        public string $resource,
        public string $resource_label,
        public string $action,
        public string $action_label,
        public bool $exists_in_source,
        public bool $exists_in_database,
        public string $sync_status,
        public bool $is_reserved,
        public int $role_count,
        public array $roles,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Permission $permission, ?bool $existsInSource = null): self
    {
        return self::fromName(
            permissionName: $permission->name,
            permission: $permission,
            existsInSource: $existsInSource ?? PermissionKey::existsInSource($permission->name),
        );
    }

    public static function fromName(string $permissionName, ?Permission $permission = null, ?bool $existsInSource = null): self
    {
        $parsed = PermissionKey::parse($permissionName);
        $app = $parsed['app'] ?? 'unknown';
        $resource = $parsed['resource'] ?? 'general';
        $action = $parsed['action'] ?? 'manage';
        $existsInDatabase = $permission instanceof Permission;
        $existsInSource ??= PermissionKey::existsInSource($permissionName);

        if ($permission instanceof Permission) {
            $permission->loadMissing('roles');
        }

        return new self(
            id: $permission instanceof Permission ? (int) $permission->id : null,
            name: $permissionName,
            guard_name: $permission?->guard_name ?? 'web',
            app: $app,
            app_label: PermissionKey::appLabel($app),
            resource: $resource,
            resource_label: PermissionKey::resourceLabel($app, $resource),
            action: $action,
            action_label: PermissionKey::actionLabel($action),
            exists_in_source: $existsInSource,
            exists_in_database: $existsInDatabase,
            sync_status: self::syncStatus($existsInSource, $existsInDatabase),
            is_reserved: false,
            role_count: $permission?->roles_count ?? $permission?->roles->count() ?? 0,
            roles: $permission?->roles->pluck('name')->sort()->values()->all() ?? [],
            created_at: $permission?->created_at instanceof CarbonInterface ? $permission->created_at->toIso8601String() : null,
            updated_at: $permission?->updated_at instanceof CarbonInterface ? $permission->updated_at->toIso8601String() : null,
        );
    }

    private static function syncStatus(bool $existsInSource, bool $existsInDatabase): string
    {
        return match (true) {
            $existsInSource && $existsInDatabase => 'synced',
            $existsInSource => 'missing',
            default => 'orphaned',
        };
    }
}
