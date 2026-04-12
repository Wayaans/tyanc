<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Permissions;

use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Rbac\PermissionData;
use App\Models\Permission;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use App\Support\Tables\AppliesTableQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class ListPermissions
{
    public function __construct(private AppliesTableQuery $tableQuery) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>,
     *     summary: array{synced: int, missing: int, orphaned: int, total: int, last_synced_at: string|null}
     * }
     */
    public function handle(User $actor, DataTableQueryData $query): array
    {
        Gate::forUser($actor)->authorize('viewAny', Permission::class);

        $databasePermissions = Permission::query()
            ->with('roles')
            ->withCount('roles')
            ->orderBy('name')
            ->get()
            ->keyBy('name');

        $sourcePermissionNames = collect(PermissionKey::all());

        /** @var Collection<int, array<string, mixed>> $permissions */
        $permissions = $sourcePermissionNames
            ->merge($databasePermissions->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $permissionName) use ($databasePermissions, $sourcePermissionNames): array {
                $permission = $databasePermissions->get($permissionName);

                return PermissionData::fromName(
                    permissionName: $permissionName,
                    permission: $permission,
                    existsInSource: $sourcePermissionNames->contains($permissionName),
                )->toArray();
            });

        $filters = $this->filters($permissions);

        return [
            ...$this->tableQuery->handle(
                items: $permissions,
                query: $query,
                sorts: [
                    'name' => 'name',
                    'app' => 'app_label',
                    'resource' => 'resource_label',
                    'action' => 'action_label',
                    'role_count' => 'role_count',
                    'sync_status' => 'sync_status',
                    'created_at' => 'created_at',
                ],
                filters: [
                    'search' => fn (array $row, mixed $value): bool => $this->matchesSearch($row, $value),
                    'app' => 'app',
                    'resource' => 'resource',
                    'action' => 'action',
                    'role' => fn (array $row, mixed $value): bool => $this->matchesRole($row, $value),
                    'status' => 'sync_status',
                    'sync_status' => 'sync_status',
                ],
            ),
            'filters' => $filters,
            'summary' => [
                'synced' => $permissions->where('sync_status', 'synced')->count(),
                'missing' => $permissions->where('sync_status', 'missing')->count(),
                'orphaned' => $permissions->where('sync_status', 'orphaned')->count(),
                'total' => $permissions->count(),
                'last_synced_at' => (($lastSyncedAt = $databasePermissions
                    ->whereIn('name', $sourcePermissionNames->all())
                    ->max('updated_at')) instanceof CarbonInterface)
                    ? $lastSyncedAt->toIso8601String()
                    : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesSearch(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        $search = mb_strtolower(mb_trim((string) $value));

        if ($search === '') {
            return true;
        }

        if (collect(['name', 'app_label', 'resource_label', 'action_label'])
            ->contains(fn (string $key): bool => str_contains(mb_strtolower((string) ($row[$key] ?? '')), $search))) {
            return true;
        }

        return collect(is_array($row['roles'] ?? null) ? $row['roles'] : [])->contains(
            fn (mixed $role): bool => is_string($role) && str_contains(mb_strtolower($role), $search),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesRole(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        $role = mb_strtolower(mb_trim((string) $value));

        if ($role === '') {
            return true;
        }

        return collect(is_array($row['roles'] ?? null) ? $row['roles'] : [])
            ->contains(fn (mixed $assignedRole): bool => is_string($assignedRole) && mb_strtolower($assignedRole) === $role);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $permissions
     * @return array<int, array{id: string, label: string, type: string, placeholder?: string, options?: array<int, array{label: string, value: string}>}>
     */
    private function filters(Collection $permissions): array
    {
        $makeOptions = fn (string $valueKey, string $labelKey): array => $permissions
            ->map(fn (array $row): array => [
                'value' => (string) ($row[$valueKey] ?? ''),
                'label' => (string) ($row[$labelKey] ?? ''),
            ])
            ->filter(fn (array $option): bool => $option['value'] !== '' && $option['label'] !== '')
            ->unique('value')
            ->sortBy('label')
            ->values()
            ->all();

        return [
            [
                'id' => 'search',
                'label' => 'Permissions',
                'type' => 'text',
                'placeholder' => 'Search permission catalog',
            ],
            [
                'id' => 'status',
                'label' => 'Sync status',
                'type' => 'select',
                'options' => [
                    ['label' => 'All statuses', 'value' => 'all'],
                    ['label' => 'Synced', 'value' => 'synced'],
                    ['label' => 'Missing in database', 'value' => 'missing'],
                    ['label' => 'Orphaned in database', 'value' => 'orphaned'],
                ],
            ],
            [
                'id' => 'app',
                'label' => 'App',
                'type' => 'select',
                'options' => $makeOptions('app', 'app_label'),
            ],
            [
                'id' => 'resource',
                'label' => 'Resource',
                'type' => 'select',
                'options' => $makeOptions('resource', 'resource_label'),
            ],
            [
                'id' => 'action',
                'label' => 'Action',
                'type' => 'select',
                'options' => $makeOptions('action', 'action_label'),
            ],
            [
                'id' => 'role',
                'label' => 'Role',
                'type' => 'select',
                'options' => $permissions
                    ->flatMap(fn (array $row): array => array_map(
                        fn (string $role): array => ['label' => $role, 'value' => $role],
                        is_array($row['roles'] ?? null) ? $row['roles'] : [],
                    ))
                    ->unique('value')
                    ->sortBy('label')
                    ->values()
                    ->all(),
            ],
        ];
    }
}
