<?php

declare(strict_types=1);

namespace App\Support\Tables;

use App\Data\Tables\DataTableQueryData;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

final class AppliesTableQuery
{
    use Macroable;

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $items
     * @param  array<string, callable(mixed): mixed|string>  $sorts
     * @param  array<string, callable(mixed, mixed): bool|string>  $filters
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     meta: array{
     *         total: int,
     *         from: int|null,
     *         to: int|null,
     *         page: int,
     *         per_page: int,
     *         last_page: int,
     *         has_pages: bool
     *     },
     *     query: DataTableQueryData
     * }
     */
    public function handle(
        Collection $items,
        DataTableQueryData $query,
        array $sorts = [],
        array $filters = [],
    ): array {
        $filtered = $this->applyFilters($items, $query, $filters);
        $sorted = $this->applySorting($filtered, $query, $sorts);
        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $query->per_page));
        $page = min($query->page, $lastPage);
        $rows = $sorted->forPage($page, $query->per_page)->values();
        $from = $total === 0 ? null : (($page - 1) * $query->per_page) + 1;
        $to = $total === 0 ? null : $from + $rows->count() - 1;

        /** @var list<array<string, mixed>> $resolvedRows */
        $resolvedRows = $rows->map(fn (mixed $row): array => is_array($row) ? $row : (array) $row)->all();

        return [
            'rows' => $resolvedRows,
            'meta' => [
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'page' => $page,
                'per_page' => $query->per_page,
                'last_page' => $lastPage,
                'has_pages' => $lastPage > 1,
            ],
            'query' => $query->withPage($page),
        ];
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $items
     * @param  array<string, callable(mixed, mixed): bool|string>  $filters
     * @return Collection<int, TValue>
     */
    private function applyFilters(Collection $items, DataTableQueryData $query, array $filters): Collection
    {
        return Collection::make($query->filter)
            ->reduce(function (Collection $rows, mixed $value, string $key) use ($filters): Collection {
                $resolver = $filters[$key] ?? $key;

                return $rows->filter(fn (mixed $row): bool => $this->matchesFilter($row, $resolver, $value));
            }, $items)
            ->values();
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $items
     * @param  array<string, callable(mixed): mixed|string>  $sorts
     * @return Collection<int, TValue>
     */
    private function applySorting(Collection $items, DataTableQueryData $query, array $sorts): Collection
    {
        if ($query->sort === []) {
            return $items->values();
        }

        return $items->sort(function (mixed $left, mixed $right) use ($query, $sorts): int {
            foreach ($query->sort as $sort) {
                $descending = str_starts_with($sort, '-');
                $column = $descending ? mb_substr($sort, 1) : $sort;
                $resolver = $sorts[$column] ?? $column;
                $comparison = $this->compareValues(
                    $this->resolveValue($left, $resolver),
                    $this->resolveValue($right, $resolver),
                );

                if ($comparison === 0) {
                    continue;
                }

                return $descending ? $comparison * -1 : $comparison;
            }

            return 0;
        })->values();
    }

    /**
     * @param  callable(mixed, mixed): bool|string  $resolver
     */
    private function matchesFilter(mixed $row, callable|string $resolver, mixed $filterValue): bool
    {
        if (! is_string($resolver)) {
            return (bool) $resolver($row, $filterValue);
        }

        $actual = $this->resolveValue($row, $resolver);

        if (is_array($filterValue)) {
            return Collection::make($filterValue)
                ->contains(fn (mixed $expected): bool => $this->matchesExpectedValue($actual, $expected));
        }

        return $this->matchesExpectedValue($actual, $filterValue);
    }

    private function matchesExpectedValue(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || ! is_scalar($actual) || ! is_scalar($expected)) {
            return false;
        }

        $actualValue = mb_strtolower(mb_trim((string) $actual));
        $expectedValue = mb_strtolower(mb_trim((string) $expected));

        if ($expectedValue === '') {
            return true;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (string) $actual === (string) $expected;
        }

        return str_contains($actualValue, $expectedValue);
    }

    /**
     * @param  callable(mixed): mixed|string  $resolver
     */
    private function resolveValue(mixed $row, callable|string $resolver): mixed
    {
        if (! is_string($resolver)) {
            return $resolver($row);
        }

        return data_get($row, $resolver);
    }

    private function compareValues(mixed $left, mixed $right): int
    {
        $leftValue = $this->normalizeValue($left);
        $rightValue = $this->normalizeValue($right);

        if ($leftValue === $rightValue) {
            return 0;
        }

        return $leftValue <=> $rightValue;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->getTimestamp();
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strtolower($value);
        }

        return $value === null ? '' : mb_strtolower(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
