<script setup lang="ts">
import {
    FlexRender,
    getCoreRowModel,
    useVueTable,
    type ColumnDef,
} from '@tanstack/vue-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, toRef } from 'vue';
import DataTableEmptyState from '@/components/admin/DataTableEmptyState.vue';
import DataTableErrorState from '@/components/admin/DataTableErrorState.vue';
import DataTableLoadingState from '@/components/admin/DataTableLoadingState.vue';
import DataTablePagination from '@/components/admin/DataTablePagination.vue';
import DataTableToolbar from '@/components/admin/DataTableToolbar.vue';
import { useDataTableQuery } from '@/composables/useDataTableQuery';
import { useTranslations } from '@/lib/translations';
import { cn } from '@/lib/utils';
import type {
    DataTableFilterDefinition,
    DataTableMeta,
    DataTableQuery,
} from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type DataRow = Record<string, unknown>;
type RouteFactory = (options?: RouteQueryOptions) => RouteDefinition<'get'>;

const props = withDefaults(
    defineProps<{
        columns: ColumnDef<DataRow, unknown>[];
        rows: DataRow[];
        meta: DataTableMeta;
        query: DataTableQuery;
        filters?: DataTableFilterDefinition[];
        route: RouteFactory;
        only?: string[];
        loading?: boolean;
        error?: string | null;
        emptyTitle?: string;
        emptyDescription?: string;
    }>(),
    {
        filters: () => [],
        only: () => [],
        loading: false,
        error: null,
        emptyTitle: undefined,
        emptyDescription: undefined,
    },
);

const { __ } = useTranslations();
const tableQuery = useDataTableQuery({
    route: props.route,
    query: toRef(props, 'query'),
    only: props.only,
});

const table = useVueTable<DataRow>({
    get data() {
        return props.rows;
    },
    columns: props.columns,
    getCoreRowModel: getCoreRowModel(),
    enableRowSelection: true,
    enableMultiSort: true,
    manualPagination: true,
    manualSorting: true,
    get pageCount() {
        return props.meta.last_page;
    },
    getRowId: (row, index) =>
        typeof row.id === 'string' || typeof row.id === 'number'
            ? String(row.id)
            : `${index}`,
    state: {
        get sorting() {
            return tableQuery.sorting.value;
        },
        get pagination() {
            return tableQuery.pagination.value;
        },
        get columnVisibility() {
            return tableQuery.columnVisibility.value;
        },
        get rowSelection() {
            return tableQuery.rowSelection.value;
        },
    },
    onSortingChange: tableQuery.setSorting,
    onPaginationChange: tableQuery.setPagination,
    onColumnVisibilityChange: tableQuery.setColumnVisibility,
    onRowSelectionChange: tableQuery.setRowSelection,
});

const hasRows = computed(() => props.rows.length > 0);

const sortingIcon = (value: false | 'asc' | 'desc') => {
    if (value === 'asc') {
        return ArrowUp;
    }

    if (value === 'desc') {
        return ArrowDown;
    }

    return ArrowUpDown;
};
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90 shadow-none"
    >
        <DataTableToolbar
            :table="table"
            :filters="props.filters"
            :active-filters="tableQuery.appliedFilters"
            :draft-filters="tableQuery.draftFilters"
            :active-filter-count="tableQuery.activeFilterCount"
            :selected-row-count="tableQuery.selectedRowCount"
            @apply-filters="tableQuery.applyFilters"
            @clear-filters="tableQuery.clearFilters"
            @remove-filter="tableQuery.removeFilter"
            @update:filter="tableQuery.setFilterValue"
        />

        <div class="p-4">
            <DataTableErrorState
                v-if="props.error"
                :description="props.error"
                @retry="tableQuery.refresh"
            />
            <DataTableLoadingState v-else-if="props.loading" />
            <DataTableEmptyState
                v-else-if="!hasRows"
                :title="props.emptyTitle"
                :description="props.emptyDescription"
            />
            <div
                v-else
                class="overflow-hidden rounded-2xl border border-sidebar-border/70"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-left text-sm">
                        <thead class="bg-sidebar/35 text-muted-foreground">
                            <tr
                                v-for="headerGroup in table.getHeaderGroups()"
                                :key="headerGroup.id"
                            >
                                <th
                                    v-for="header in headerGroup.headers"
                                    :key="header.id"
                                    class="px-4 py-3 font-medium"
                                >
                                    <div
                                        v-if="!header.isPlaceholder"
                                        :class="
                                            cn(
                                                'flex items-center gap-2',
                                                header.column.getCanSort()
                                                    ? 'cursor-pointer select-none'
                                                    : '',
                                            )
                                        "
                                        @click="
                                            header.column.getToggleSortingHandler()?.(
                                                $event,
                                            )
                                        "
                                    >
                                        <FlexRender
                                            :render="
                                                header.column.columnDef.header
                                            "
                                            :props="header.getContext()"
                                        />
                                        <component
                                            v-if="header.column.getCanSort()"
                                            :is="
                                                sortingIcon(
                                                    header.column.getIsSorted(),
                                                )
                                            "
                                            class="size-3.5 text-muted-foreground"
                                        />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in table.getRowModel().rows"
                                :key="row.id"
                                :class="
                                    cn(
                                        'border-t border-sidebar-border/70 transition-colors',
                                        row.getIsSelected()
                                            ? 'bg-sidebar/20'
                                            : 'bg-background',
                                    )
                                "
                            >
                                <td
                                    v-for="cell in row.getVisibleCells()"
                                    :key="cell.id"
                                    class="px-4 py-3 align-middle text-foreground"
                                >
                                    <FlexRender
                                        :render="cell.column.columnDef.cell"
                                        :props="cell.getContext()"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataTablePagination :table="table" :meta="props.meta" />
            </div>
        </div>

        <div
            class="border-t border-sidebar-border/70 px-4 py-3 text-xs text-muted-foreground"
        >
            {{ __('Shift-click headers to sort by multiple columns.') }}
        </div>
    </section>
</template>
