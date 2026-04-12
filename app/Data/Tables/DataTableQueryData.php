<?php

declare(strict_types=1);

namespace App\Data\Tables;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class DataTableQueryData extends Data
{
    /**
     * @param  array<int, string>  $sort
     * @param  array<string, string|array<int, string>>  $filter
     * @param  array<string, bool>  $columns
     */
    public function __construct(
        public int $page,
        public int $per_page,
        public array $sort,
        public array $filter,
        public array $columns,
    ) {}

    /**
     * @param  array<int, string>  $allowedSorts
     * @param  array<int, string>  $allowedFilters
     * @param  array<int, string>  $defaultSort
     * @param  array<int, string>  $allowedColumns
     */
    public static function fromRequest(
        Request $request,
        array $allowedSorts = [],
        array $allowedFilters = [],
        array $defaultSort = [],
        array $allowedColumns = [],
    ): self {
        return new self(
            page: max(1, $request->integer('page', 1)),
            per_page: min(max($request->integer('per_page', 10), 5), 100),
            sort: self::sanitizeSort(
                sort: $request->input('sort'),
                allowedSorts: $allowedSorts,
                defaultSort: $defaultSort,
            ),
            filter: self::sanitizeFilter(
                filter: $request->input('filter'),
                allowedFilters: $allowedFilters,
            ),
            columns: self::sanitizeColumns(
                columns: $request->input('columns'),
                allowedColumns: $allowedColumns,
            ),
        );
    }

    public function withPage(int $page): self
    {
        return new self(
            page: max(1, $page),
            per_page: $this->per_page,
            sort: $this->sort,
            filter: $this->filter,
            columns: $this->columns,
        );
    }

    /**
     * @param  array<int, string>  $allowedSorts
     * @param  array<int, string>  $defaultSort
     * @return array<int, string>
     */
    private static function sanitizeSort(mixed $sort, array $allowedSorts, array $defaultSort): array
    {
        $values = match (true) {
            is_array($sort) => $sort,
            is_string($sort) => [$sort],
            default => [],
        };

        $allowed = array_flip($allowedSorts);

        $resolved = Collection::make($values)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(function (string $value): ?string {
                $value = mb_trim($value);

                return $value === '' ? null : $value;
            })
            ->filter()
            ->map(function (string $value) use ($allowed): ?string {
                $column = str_starts_with($value, '-') ? mb_substr($value, 1) : $value;

                if ($column === '' || ($allowed !== [] && ! array_key_exists($column, $allowed))) {
                    return null;
                }

                return $value;
            })
            ->filter()
            ->values()
            ->all();

        if ($resolved !== []) {
            return $resolved;
        }

        return Collection::make($defaultSort)
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $allowedFilters
     * @return array<string, string|array<int, string>>
     */
    private static function sanitizeFilter(mixed $filter, array $allowedFilters): array
    {
        if (! is_array($filter)) {
            return [];
        }

        $allowed = array_flip($allowedFilters);

        return Collection::make($filter)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && ($allowed === [] || array_key_exists($key, $allowed)))
            ->map(function (mixed $value): string|array|null {
                if (is_array($value)) {
                    $resolved = Collection::make($value)
                        ->filter(fn (mixed $item): bool => is_scalar($item) && mb_trim((string) $item) !== '')
                        ->map(fn (mixed $item): string => mb_trim((string) $item))
                        ->values()
                        ->all();

                    return $resolved === [] ? null : $resolved;
                }

                if (! is_scalar($value)) {
                    return null;
                }

                $resolved = mb_trim((string) $value);

                return $resolved === '' ? null : $resolved;
            })
            ->filter(fn (mixed $value): bool => $value !== null)
            ->all();
    }

    /**
     * @param  array<int, string>  $allowedColumns
     * @return array<string, bool>
     */
    private static function sanitizeColumns(mixed $columns, array $allowedColumns): array
    {
        if (! is_array($columns)) {
            return [];
        }

        $allowed = array_flip($allowedColumns);

        return Collection::make($columns)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && ($allowed === [] || array_key_exists($key, $allowed)))
            ->map(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false)
            ->all();
    }
}
