import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { h } from 'vue';
import AppActionsDropdown from '@/components/tyanc/apps/AppActionsDropdown.vue';
import AppStatusBadge from '@/components/tyanc/apps/AppStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { __ } from '@/lib/translations';
import type { AppRow } from '@/types';

const columnHelper = createColumnHelper<AppRow>();

export function createAppTableColumns(): ColumnDef<AppRow>[] {
    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }: { table: TanStackTable<AppRow> }) =>
                h(Checkbox, {
                    modelValue: table.getIsAllPageRowsSelected()
                        ? true
                        : table.getIsSomePageRowsSelected()
                          ? 'indeterminate'
                          : false,
                    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                        table.toggleAllPageRowsSelected(Boolean(value)),
                    'aria-label': __('Select all rows'),
                }),
            cell: ({ row }) =>
                h(Checkbox, {
                    modelValue: row.getIsSelected(),
                    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                        row.toggleSelected(Boolean(value)),
                    'aria-label': __('Select row'),
                }),
            meta: { label: 'Selection' },
        }),

        columnHelper.accessor('label', {
            header: __('App'),
            enableSorting: true,
            cell: ({ row }) =>
                h('div', { class: 'min-w-48 space-y-0.5' }, [
                    h(
                        'p',
                        { class: 'font-medium text-foreground leading-none' },
                        row.original.label,
                    ),
                    h(
                        'p',
                        { class: 'font-mono text-xs text-muted-foreground' },
                        row.original.key,
                    ),
                ]),
            meta: { label: 'App' },
        }),

        columnHelper.accessor('route_prefix', {
            header: __('Route prefix'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'font-mono text-xs text-muted-foreground' },
                    `/${String(getValue())}`,
                ),
            meta: { label: 'Route prefix' },
        }),

        columnHelper.accessor('permission_namespace', {
            header: __('Namespace'),
            enableSorting: false,
            cell: ({ getValue }) =>
                h(
                    Badge,
                    { variant: 'outline', class: 'font-mono text-xs' },
                    { default: () => String(getValue()) },
                ),
            meta: { label: 'Namespace' },
        }),

        columnHelper.accessor('sort_order', {
            header: __('Order'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-muted-foreground text-sm tabular-nums' },
                    String(getValue()),
                ),
            meta: { label: 'Order' },
        }),

        columnHelper.accessor('enabled', {
            header: __('Status'),
            enableSorting: true,
            cell: ({ row }) =>
                h(AppStatusBadge, {
                    enabled: Boolean(row.original.enabled),
                    isSystem: Boolean(row.original.is_system),
                }),
            meta: { label: 'Status' },
        }),

        columnHelper.display({
            id: 'actions',
            enableSorting: false,
            enableHiding: false,
            header: '',
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'flex justify-end' },
                    h(AppActionsDropdown, {
                        appKey: row.original.key,
                        appLabel: row.original.label,
                        isSystem: Boolean(row.original.is_system),
                    }),
                ),
            meta: { label: 'Actions' },
        }),
    ] as ColumnDef<AppRow>[];
}
