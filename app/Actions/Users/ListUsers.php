<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Tables\DataTableQueryData;
use App\Data\Users\UserIndexData;
use App\Enums\UserStatus;
use App\Http\Requests\Tyanc\UserIndexRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListUsers
{
    /**
     * @return array{
     *     rows: list<UserIndexData>,
     *     meta: array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool},
     *     query: DataTableQueryData,
     *     filters: list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     * }
     */
    public function handle(User $actor, UserIndexRequest $request): array
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        $tableQuery = $request->tableQuery();
        $queryRequest = $request->duplicate([
            ...$request->query(),
            'sort' => implode(',', $tableQuery->sort),
        ]);

        $users = QueryBuilder::for(
            subject: User::query()->with(['profile', 'roles', 'permissions']),
            request: $queryRequest,
        )
            ->allowedFilters(
                AllowedFilter::callback('search', $this->applySearch(...)),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('locale'),
                AllowedFilter::exact('role', 'roles.name'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts(
                AllowedSort::field('name', 'username'),
                'email',
                'status',
                'locale',
                'last_login_at',
                'created_at',
            )
            ->defaultSort('-created_at')
            ->paginate(
                perPage: $tableQuery->per_page,
                page: $tableQuery->page,
            )
            ->withQueryString();

        return [
            'rows' => Collection::make($users->items())
                ->map(fn (User $user): UserIndexData => UserIndexData::fromModel($user))
                ->all(),
            'meta' => $this->meta($users),
            'query' => $tableQuery->withPage($users->currentPage()),
            'filters' => $this->filters(),
        ];
    }

    private function applySearch(Builder $query, mixed $value): void
    {
        if (! is_scalar($value)) {
            return;
        }

        $search = mb_trim((string) $value);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('username', 'like', sprintf('%%%s%%', $search))
                ->orWhere('email', 'like', sprintf('%%%s%%', $search))
                ->orWhere('last_login_ip', 'like', sprintf('%%%s%%', $search))
                ->orWhereHas('profile', function (Builder $profileQuery) use ($search): void {
                    $profileQuery
                        ->where('first_name', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('last_name', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('city', 'like', sprintf('%%%s%%', $search))
                        ->orWhere('company_name', 'like', sprintf('%%%s%%', $search));
                });
        });
    }

    /**
     * @return list<array{id: string, label: string, type: string, placeholder?: string, options?: list<array{label: string, value: string}>}>
     */
    private function filters(): array
    {
        return [
            [
                'id' => 'search',
                'label' => 'Users',
                'type' => 'text',
                'placeholder' => 'Search users',
            ],
            [
                'id' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => Collection::make(UserStatus::cases())
                    ->map(fn (UserStatus $status): array => [
                        'label' => $status->value,
                        'value' => $status->value,
                    ])
                    ->values()
                    ->all(),
            ],
            [
                'id' => 'role',
                'label' => 'Role',
                'type' => 'select',
                'options' => Role::query()
                    ->orderByDesc('level')
                    ->orderBy('name')
                    ->get(['name'])
                    ->map(fn (Role $role): array => [
                        'label' => $role->name,
                        'value' => $role->name,
                    ])
                    ->values()
                    ->all(),
            ],
            [
                'id' => 'trashed',
                'label' => 'Archived',
                'type' => 'select',
                'options' => [
                    ['label' => 'Active users', 'value' => 'without'],
                    ['label' => 'With archived users', 'value' => 'with'],
                    ['label' => 'Archived users only', 'value' => 'only'],
                ],
            ],
        ];
    }

    /**
     * @return array{total: int, from: int|null, to: int|null, page: int, per_page: int, last_page: int, has_pages: bool}
     */
    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'has_pages' => $paginator->hasPages(),
        ];
    }
}
