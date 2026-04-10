<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Roles;

use App\Data\Tables\DataTableQueryData;
use App\Data\Tyanc\Rbac\RoleData;
use App\Models\Role;
use App\Models\User;
use App\Support\Tables\AppliesTableQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class ListRoles
{
    public function __construct(private AppliesTableQuery $tableQuery) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     * }
     */
    public function handle(User $actor, DataTableQueryData $query): array
    {
        Gate::forUser($actor)->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with('permissions')
            ->withCount(['permissions', 'users'])
            ->orderByDesc('level')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => RoleData::fromModel($role)->toArray());

        return [
            ...$this->tableQuery->handle(
                items: $roles,
                query: $query,
                sorts: [
                    'name' => 'name',
                    'level' => 'level',
                    'permission_count' => 'permission_count',
                    'user_count' => 'user_count',
                    'created_at' => 'created_at',
                ],
                filters: [
                    'search' => fn (array $row, mixed $value): bool => $this->matchesSearch($row, $value),
                    'reserved' => fn (array $row, mixed $value): bool => $this->matchesReserved($row, $value),
                ],
            ),
            'filters' => $this->filters(),
        ];
    }

    private function matchesSearch(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        $search = mb_strtolower(mb_trim((string) $value));

        if ($search === '') {
            return true;
        }

        return str_contains(mb_strtolower((string) $row['name']), $search)
            || Collection::make($row['permissions'] ?? [])->contains(
                fn (mixed $permission): bool => is_string($permission) && str_contains(mb_strtolower($permission), $search),
            );
    }

    private function matchesReserved(array $row, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        return match ((string) $value) {
            'reserved' => (bool) ($row['is_reserved'] ?? false),
            'custom' => ! (bool) ($row['is_reserved'] ?? false),
            default => true,
        };
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Roles',
                'type' => 'text',
                'placeholder' => 'Search roles',
            ],
            [
                'id' => 'reserved',
                'label' => 'Type',
                'type' => 'select',
                'options' => [
                    ['label' => 'All roles', 'value' => 'all'],
                    ['label' => 'Reserved', 'value' => 'reserved'],
                    ['label' => 'Custom', 'value' => 'custom'],
                ],
            ],
        ];
    }
}
