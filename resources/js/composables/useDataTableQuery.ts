import { router } from '@inertiajs/vue3';
import type {
    PaginationState,
    RowSelectionState,
    SortingState,
    Updater,
    VisibilityState,
} from '@tanstack/vue-table';
import { computed, ref, unref, watch, type Ref } from 'vue';
import { toUrl } from '@/lib/utils';
import type { DataTableQuery } from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type RouteFactory = (options?: RouteQueryOptions) => RouteDefinition<'get'>;
type FilterValue = string | string[];

type UseDataTableQueryOptions = {
    route: RouteFactory;
    query: Ref<DataTableQuery>;
    only?: string[];
};

const resolveUpdater = <T>(updater: Updater<T>, previous: T): T =>
    typeof updater === 'function'
        ? (updater as (value: T) => T)(previous)
        : updater;

const toSortingState = (sort: string[]): SortingState =>
    sort.map((value) => ({
        id: value.startsWith('-') ? value.slice(1) : value,
        desc: value.startsWith('-'),
    }));

const fromSortingState = (sorting: SortingState): string[] =>
    sorting.map((value) => (value.desc ? `-${value.id}` : value.id));

const toVisibilityState = (
    columns: Record<string, boolean>,
): VisibilityState => ({ ...columns });

const fromVisibilityState = (
    visibility: VisibilityState,
): Record<string, boolean> =>
    Object.fromEntries(
        Object.entries(visibility).filter(([, value]) => value === false),
    );

const sanitizeFilters = (
    filters: Record<string, FilterValue>,
): Record<string, FilterValue> => {
    const resolved = new Map<string, FilterValue>();

    for (const [key, value] of Object.entries(filters)) {
        if (Array.isArray(value)) {
            const filteredValues = value.filter((item) => item !== '');

            if (filteredValues.length > 0) {
                resolved.set(key, filteredValues);
            }

            continue;
        }

        if (value !== '') {
            resolved.set(key, value);
        }
    }

    return Object.fromEntries(resolved.entries());
};

const cloneFilters = (
    filters: Record<string, string | string[]>,
): Record<string, FilterValue> =>
    Object.fromEntries(
        Object.entries(filters).map(([key, value]) => [
            key,
            Array.isArray(value) ? [...value] : value,
        ]),
    );

export function useDataTableQuery(options: UseDataTableQueryOptions) {
    const sorting = ref<SortingState>(toSortingState(options.query.value.sort));
    const pagination = ref<PaginationState>({
        pageIndex: Math.max(0, options.query.value.page - 1),
        pageSize: options.query.value.per_page,
    });
    const rowSelection = ref<RowSelectionState>({});
    const columnVisibility = ref<VisibilityState>(
        toVisibilityState(options.query.value.columns),
    );
    const appliedFilters = ref<Record<string, FilterValue>>(
        cloneFilters(options.query.value.filter),
    );
    const draftFilters = ref<Record<string, FilterValue>>(
        cloneFilters(options.query.value.filter),
    );

    const queryState = computed(() => unref(options.query));

    watch(
        queryState,
        (query) => {
            sorting.value = toSortingState(query.sort);
            pagination.value = {
                pageIndex: Math.max(0, query.page - 1),
                pageSize: query.per_page,
            };
            columnVisibility.value = toVisibilityState(query.columns);
            appliedFilters.value = cloneFilters(query.filter);
            draftFilters.value = cloneFilters(query.filter);
            rowSelection.value = {};
        },
        { deep: true },
    );

    const activeFilterCount = computed(
        () => Object.keys(sanitizeFilters(appliedFilters.value)).length,
    );
    const selectedRowCount = computed(
        () => Object.keys(rowSelection.value).length,
    );

    const visit = (next: Partial<DataTableQuery> = {}): void => {
        const query: DataTableQuery = {
            page: next.page ?? pagination.value.pageIndex + 1,
            per_page: next.per_page ?? pagination.value.pageSize,
            sort: next.sort ?? fromSortingState(sorting.value),
            filter: next.filter ?? sanitizeFilters(appliedFilters.value),
            columns:
                next.columns ?? fromVisibilityState(columnVisibility.value),
        };

        router.visit(toUrl(options.route({ query })), {
            method: 'get',
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: options.only,
        });
    };

    const setSorting = (updater: Updater<SortingState>): void => {
        sorting.value = resolveUpdater(updater, sorting.value);
        visit({ page: 1, sort: fromSortingState(sorting.value) });
    };

    const setPagination = (updater: Updater<PaginationState>): void => {
        pagination.value = resolveUpdater(updater, pagination.value);
        visit({
            page: pagination.value.pageIndex + 1,
            per_page: pagination.value.pageSize,
        });
    };

    const setColumnVisibility = (updater: Updater<VisibilityState>): void => {
        columnVisibility.value = resolveUpdater(
            updater,
            columnVisibility.value,
        );
        visit({ columns: fromVisibilityState(columnVisibility.value) });
    };

    const setRowSelection = (updater: Updater<RowSelectionState>): void => {
        rowSelection.value = resolveUpdater(updater, rowSelection.value);
    };

    const setFilterValue = (key: string, value: FilterValue): void => {
        draftFilters.value = {
            ...draftFilters.value,
            [key]: Array.isArray(value) ? [...value] : value,
        };
    };

    const applyFilters = (): void => {
        appliedFilters.value = sanitizeFilters(draftFilters.value);
        visit({ page: 1, filter: sanitizeFilters(draftFilters.value) });
    };

    const clearFilters = (): void => {
        draftFilters.value = {};
        appliedFilters.value = {};
        visit({ page: 1, filter: {} });
    };

    const removeFilter = (key: string): void => {
        const nextFilters = { ...appliedFilters.value };
        delete nextFilters[key];
        draftFilters.value = nextFilters;
        appliedFilters.value = nextFilters;
        visit({ page: 1, filter: sanitizeFilters(nextFilters) });
    };

    const refresh = (): void => {
        visit();
    };

    return {
        activeFilterCount,
        appliedFilters,
        applyFilters,
        clearFilters,
        columnVisibility,
        draftFilters,
        pagination,
        refresh,
        removeFilter,
        rowSelection,
        selectedRowCount,
        setColumnVisibility,
        setFilterValue,
        setPagination,
        setRowSelection,
        setSorting,
        sorting,
    };
}
