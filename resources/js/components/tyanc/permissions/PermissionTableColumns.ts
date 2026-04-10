import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { Lock } from 'lucide-vue-next';
import { h } from 'vue';
import PermissionNamespaceBadge from '@/components/tyanc/permissions/PermissionNamespaceBadge.vue';
import PermissionSourceBadge from '@/components/tyanc/permissions/PermissionSourceBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { __ } from '@/lib/translations';
import type { PermissionRow } from '@/types';

const columnHelper = createColumnHelper<PermissionRow>();

export function createPermissionTableColumns(): ColumnDef<PermissionRow>[] {
    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }: { table: TanStackTable<PermissionRow> }) =>
                h(Checkbox, {
                    modelValue: table.getIsAllPageRowsSelected()
                        ? true
                        : table.getIsSomePageRowsSelected()
                          ? 'indeterminate'
                          : false,
                    'onUpdate:modelValue': (
                        value: boolean | 'indeterminate',
                    ) => table.toggleAllPageRowsSelected(Boolean(value)),
                    'aria-label': __('Select all rows'),
                }),
            cell: ({ row }) =>
                h(Checkbox, {
                    modelValue: row.getIsSelected(),
                    'onUpdate:modelValue': (
                        value: boolean | 'indeterminate',
                    ) => row.toggleSelected(Boolean(value)),
                    'aria-label': __('Select row'),
                }),
            meta: { label: 'Selection' },
        }),

        columnHelper.accessor('name', {
            header: __('Permission'),
            enableSorting: true,
            cell: ({ row }) =>
                h('div', { class: 'flex items-center gap-2 min-w-56' }, [
                    h(
                        'span',
                        { class: 'font-mono text-sm text-foreground' },
                        row.original.name,
                    ),
                    row.original.is_reserved
                        ? h(Lock, {
                              class: 'size-3 text-muted-foreground/60 shrink-0',
                          })
                        : null,
                ]),
            meta: { label: 'Permission' },
        }),

        columnHelper.accessor('app', {
            header: __('App'),
            enableSorting: true,
            cell: ({ row }) =>
                h(PermissionNamespaceBadge, {
                    namespace: row.original.app_label,
                }),
            meta: { label: 'App' },
        }),

        columnHelper.accessor('resource', {
            header: __('Resource'),
            enableSorting: false,
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'text-sm text-muted-foreground' },
                    row.original.resource_label || '—',
                ),
            meta: { label: 'Resource' },
        }),

        columnHelper.accessor('action', {
            header: __('Action'),
            enableSorting: false,
            cell: ({ row }) =>
                h(
                    Badge,
                    { variant: 'secondary', class: 'text-xs' },
                    { default: () => row.original.action_label },
                ),
            meta: { label: 'Action' },
        }),

        columnHelper.accessor('role_count', {
            header: __('Roles'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    {
                        class: 'tabular-nums text-sm text-muted-foreground',
                    },
                    String(getValue()),
                ),
            meta: { label: 'Roles' },
        }),

        columnHelper.accessor('sync_status', {
            header: __('Status'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(PermissionSourceBadge, {
                    status: getValue() as string | null,
                }),
            meta: { label: 'Status' },
        }),
    ] as ColumnDef<PermissionRow>[];
}
