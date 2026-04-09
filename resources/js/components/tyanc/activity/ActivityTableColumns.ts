import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { h } from 'vue';
import ActivityEventBadge from '@/components/tyanc/activity/ActivityEventBadge.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { __ } from '@/lib/translations';
import type { ActivityRow } from '@/types';

const columnHelper = createColumnHelper<ActivityRow>();

export function createActivityTableColumns(
    dateFormatter: Intl.DateTimeFormat,
): ColumnDef<ActivityRow>[] {
    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }: { table: TanStackTable<ActivityRow> }) =>
                h(Checkbox, {
                    checked: table.getIsAllPageRowsSelected(),
                    'onUpdate:checked': (value: boolean | 'indeterminate') =>
                        table.toggleAllPageRowsSelected(Boolean(value)),
                    'aria-label': __('Select all rows'),
                }),
            cell: ({ row }) =>
                h(Checkbox, {
                    checked: row.getIsSelected(),
                    'onUpdate:checked': (value: boolean | 'indeterminate') =>
                        row.toggleSelected(Boolean(value)),
                    'aria-label': __('Select row'),
                }),
            meta: { label: 'Selection' },
        }),

        columnHelper.accessor('event', {
            header: __('Event'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(ActivityEventBadge, { event: getValue() as string | null }),
            meta: { label: 'Event' },
        }),

        columnHelper.accessor('description', {
            header: __('Description'),
            enableSorting: false,
            cell: ({ getValue, row }) =>
                h('div', { class: 'space-y-0.5 min-w-52' }, [
                    h(
                        'p',
                        {
                            class: 'text-sm font-medium text-foreground leading-none',
                        },
                        String(getValue()),
                    ),
                    row.original.subject_name
                        ? h(
                              'p',
                              { class: 'text-xs text-muted-foreground' },
                              row.original.subject_name,
                          )
                        : null,
                ]),
            meta: { label: 'Description' },
        }),

        columnHelper.accessor('causer_name', {
            header: __('Caused by'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-sm text-foreground' },
                    getValue() ?? '—',
                ),
            meta: { label: 'Caused by' },
        }),

        columnHelper.accessor('log_name', {
            header: __('Log'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground font-mono' },
                    getValue() ?? '—',
                ),
            meta: { label: 'Log' },
        }),

        columnHelper.accessor('subject_type', {
            header: __('Subject type'),
            enableSorting: true,
            cell: ({ getValue }) => {
                const val = getValue();

                if (!val) {
                    return h('span', { class: 'text-muted-foreground' }, '—');
                }

                // Strip namespace, show just the class name
                const parts = String(val).split('\\');
                const className = parts[parts.length - 1] ?? val;

                return h(
                    'span',
                    { class: 'text-xs text-muted-foreground font-mono' },
                    className,
                );
            },
            meta: { label: 'Subject type' },
        }),

        columnHelper.accessor('created_at', {
            header: __('When'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    {
                        class: 'whitespace-nowrap text-xs text-muted-foreground',
                    },
                    dateFormatter.format(new Date(String(getValue()))),
                ),
            meta: { label: 'When' },
        }),
    ] as ColumnDef<ActivityRow>[];
}
