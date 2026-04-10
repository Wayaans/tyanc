import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { Lock } from 'lucide-vue-next';
import { h } from 'vue';
import RoleLevelBadge from '@/components/tyanc/roles/RoleLevelBadge.vue';
import RolePermissionSummary from '@/components/tyanc/roles/RolePermissionSummary.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { __ } from '@/lib/translations';
import type { RoleRow } from '@/types';

const columnHelper = createColumnHelper<RoleRow>();

export function createRoleTableColumns(
    onEdit: (role: RoleRow) => void,
    onAssignPermissions: (role: RoleRow) => void,
    onDelete: (role: RoleRow) => void,
): ColumnDef<RoleRow>[] {
    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }: { table: TanStackTable<RoleRow> }) =>
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
            header: __('Role'),
            enableSorting: true,
            cell: ({ row }) =>
                h('div', { class: 'flex items-center gap-2 min-w-40' }, [
                    h(
                        'p',
                        { class: 'font-medium text-foreground' },
                        row.original.name,
                    ),
                    row.original.is_reserved
                        ? h(Lock, {
                              class: 'size-3 text-muted-foreground/60 shrink-0',
                          })
                        : null,
                ]),
            meta: { label: 'Role' },
        }),

        columnHelper.accessor('level', {
            header: __('Level'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(RoleLevelBadge, { level: Number(getValue()) }),
            meta: { label: 'Level' },
        }),

        columnHelper.accessor('permission_count', {
            header: __('Permissions'),
            enableSorting: true,
            cell: ({ row }) =>
                h(RolePermissionSummary, {
                    permissionCount: row.original.permission_count,
                    permissions: row.original.permissions,
                }),
            meta: { label: 'Permissions' },
        }),

        columnHelper.accessor('guard_name', {
            header: __('Guard'),
            enableSorting: false,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'font-mono text-xs text-muted-foreground' },
                    String(getValue()),
                ),
            meta: { label: 'Guard' },
        }),

        columnHelper.accessor('created_at', {
            header: __('Created'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    {
                        class: 'whitespace-nowrap text-xs text-muted-foreground',
                    },
                    new Date(String(getValue())).toLocaleDateString(),
                ),
            meta: { label: 'Created' },
        }),

        columnHelper.display({
            id: 'actions',
            enableSorting: false,
            enableHiding: false,
            header: '',
            cell: ({ row }) => {
                if (row.original.is_reserved) {
                    return h('div', { class: 'flex justify-end' }, [
                        h(
                            'span',
                            {
                                class: 'text-xs text-muted-foreground/50 px-2',
                            },
                            __('Reserved'),
                        ),
                    ]);
                }

                return h('div', { class: 'flex justify-end gap-1' }, [
                    h(
                        'button',
                        {
                            class: 'text-xs text-primary/80 hover:text-primary px-2 py-1 rounded transition-colors',
                            onClick: () => onAssignPermissions(row.original),
                        },
                        __('Permissions'),
                    ),
                    h(
                        'button',
                        {
                            class: 'text-xs text-muted-foreground hover:text-foreground px-2 py-1 rounded transition-colors',
                            onClick: () => onEdit(row.original),
                        },
                        __('Edit'),
                    ),
                    h(
                        'button',
                        {
                            class: 'text-xs text-destructive/70 hover:text-destructive px-2 py-1 rounded transition-colors',
                            onClick: () => onDelete(row.original),
                        },
                        __('Delete'),
                    ),
                ]);
            },
            meta: { label: 'Actions' },
        }),
    ] as ColumnDef<RoleRow>[];
}
