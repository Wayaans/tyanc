<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { computed, h } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { __, useTranslations } from '@/lib/translations';
import type {
    ApprovalRequestRow,
    ApprovalStatus,
    DataTablePayload,
} from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type RouteFactory = (options?: RouteQueryOptions) => RouteDefinition<'get'>;

const props = withDefaults(
    defineProps<{
        approvalsTable: DataTablePayload<ApprovalRequestRow>;
        route: RouteFactory;
        only?: string[];
        emptyTitle?: string;
        emptyDescription?: string;
        detailHref?: (request: ApprovalRequestRow) => string;
    }>(),
    {
        only: () => [],
        emptyTitle: undefined,
        emptyDescription: undefined,
        detailHref: undefined,
    },
);

const emit = defineEmits<{
    decide: [request: ApprovalRequestRow];
}>();

const { locale } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

type StatusConfig = { label: string; badgeClass: string };

const statusConfigs: Record<ApprovalStatus, StatusConfig> = {
    draft: {
        label: 'Draft',
        badgeClass:
            'border-zinc-500/20 bg-zinc-500/10 text-zinc-700 dark:text-zinc-300',
    },
    pending: {
        label: 'Pending',
        badgeClass:
            'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    },
    in_review: {
        label: 'In review',
        badgeClass:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
    approved: {
        label: 'Approved',
        badgeClass:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    rejected: {
        label: 'Rejected',
        badgeClass:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    },
    cancelled: {
        label: 'Cancelled',
        badgeClass:
            'border-orange-500/20 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    },
    expired: {
        label: 'Expired',
        badgeClass:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    superseded: {
        label: 'Superseded',
        badgeClass:
            'border-stone-500/20 bg-stone-500/10 text-stone-700 dark:text-stone-300',
    },
};

function resolveStatusConfig(status: ApprovalStatus): StatusConfig {
    return statusConfigs[status] ?? statusConfigs.pending;
}

const columnHelper = createColumnHelper<ApprovalRequestRow>();

const columns = computed<ColumnDef<ApprovalRequestRow>[]>(() => {
    const fmt = dateFormatter.value;

    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }: { table: TanStackTable<ApprovalRequestRow> }) =>
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

        columnHelper.accessor('subject_name', {
            header: __('Subject'),
            enableSorting: false,
            cell: ({ getValue, row }) => {
                const subjectName = String(getValue());
                const href = props.detailHref?.(row.original);

                return h('div', { class: 'space-y-0.5 min-w-48' }, [
                    href
                        ? h(
                              'a',
                              {
                                  href,
                                  class: 'text-sm font-medium text-foreground leading-none hover:underline',
                                  onClick: (e: MouseEvent) => {
                                      e.preventDefault();
                                      router.visit(href);
                                  },
                              },
                              subjectName,
                          )
                        : h(
                              'p',
                              {
                                  class: 'text-sm font-medium text-foreground leading-none',
                              },
                              subjectName,
                          ),
                    h(
                        'p',
                        { class: 'text-xs text-muted-foreground' },
                        row.original.action_label,
                    ),
                ]);
            },
            meta: { label: 'Subject' },
        }),

        columnHelper.accessor('requested_by_name', {
            header: __('Requester'),
            enableSorting: false,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-sm text-foreground' },
                    String(getValue() ?? '—'),
                ),
            meta: { label: 'Requester' },
        }),

        columnHelper.accessor('status', {
            header: __('Status'),
            enableSorting: true,
            cell: ({ getValue }) => {
                const status = getValue() as ApprovalStatus;
                const config = resolveStatusConfig(status);

                return h(
                    Badge,
                    {
                        variant: 'outline',
                        class: `rounded-full text-xs ${config.badgeClass}`,
                    },
                    { default: () => __(config.label) },
                );
            },
            meta: { label: 'Status' },
        }),

        columnHelper.accessor('requested_at', {
            header: __('Requested'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    {
                        class: 'whitespace-nowrap text-xs text-muted-foreground',
                    },
                    fmt.format(new Date(String(getValue()))),
                ),
            meta: { label: 'Requested' },
        }),

        columnHelper.display({
            id: 'actions',
            enableSorting: false,
            enableHiding: false,
            header: '',
            cell: ({ row }) =>
                h('div', { class: 'flex justify-end' }, [
                    h(
                        Button,
                        {
                            variant: 'outline',
                            size: 'sm',
                            class: 'text-xs',
                            onClick: () => emit('decide', row.original),
                        },
                        { default: () => __('View') },
                    ),
                ]),
            meta: { label: 'Actions' },
        }),
    ] as ColumnDef<ApprovalRequestRow>[];
});
</script>

<template>
    <DataTable
        :columns="columns as ColumnDef<Record<string, unknown>, unknown>[]"
        :rows="props.approvalsTable.rows as Record<string, unknown>[]"
        :meta="props.approvalsTable.meta"
        :query="props.approvalsTable.query"
        :filters="props.approvalsTable.filters"
        :route="props.route"
        :only="props.only"
        :empty-title="props.emptyTitle"
        :empty-description="props.emptyDescription"
    />
</template>
