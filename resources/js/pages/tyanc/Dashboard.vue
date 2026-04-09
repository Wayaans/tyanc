<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { CheckCircle2, CircleAlert, Gauge, MinusCircle } from 'lucide-vue-next';
import { computed, h } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { dashboard } from '@/routes';
import type { DataTablePayload } from '@/types';

type Summary = {
    module_count: number;
    attention_count: number;
    average_health: number;
};

type ModuleRow = {
    id: string;
    name: string;
    status: string;
    team: string;
    records: number;
    health_score: number;
    updated_at: string;
};

const props = defineProps<{
    summary: Summary;
    modulesTable: DataTablePayload<ModuleRow>;
}>();

const { __, locale } = useTranslations();
const { dashboardBreadcrumbs } = useAppNavigation();
const columnHelper = createColumnHelper<ModuleRow>();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const statusIcon = (status: string) => {
    if (status === 'Healthy') {
        return CheckCircle2;
    }

    if (status === 'Attention') {
        return CircleAlert;
    }

    return MinusCircle;
};

const statusClassName = (status: string): string => {
    if (status === 'Healthy') {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'Attention') {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300';
};

const summaryCards = computed(() => [
    {
        title: __('Modules'),
        value: props.summary.module_count,
        description: __('Shared shell surfaces now connected to Phase 4.'),
        icon: Gauge,
    },
    {
        title: __('Needs attention'),
        value: props.summary.attention_count,
        description: __('Modules that still need follow-up before rollout.'),
        icon: CircleAlert,
    },
    {
        title: __('Average health'),
        value: `${props.summary.average_health}%`,
        description: __('Current readiness across the admin framework shell.'),
        icon: CheckCircle2,
    },
]);

const columns = computed<ColumnDef<ModuleRow>[]>(() => [
    columnHelper.display({
        id: 'select',
        enableSorting: false,
        enableHiding: false,
        header: ({ table }: { table: TanStackTable<ModuleRow> }) =>
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
        meta: {
            label: 'Selection',
        },
    }),
    columnHelper.accessor('name', {
        header: __('Module'),
        cell: ({ row }) =>
            h('div', { class: 'min-w-44 space-y-1' }, [
                h(
                    'p',
                    { class: 'font-medium text-foreground' },
                    __(row.original.name),
                ),
                h(
                    'p',
                    { class: 'text-xs text-muted-foreground' },
                    __(row.original.team),
                ),
            ]),
        meta: {
            label: 'Module',
        },
    }),
    columnHelper.accessor('status', {
        header: __('Status'),
        cell: ({ getValue }) => {
            const status = String(getValue());

            return h(
                Badge,
                {
                    variant: 'outline',
                    class: `gap-1.5 rounded-full ${statusClassName(status)}`,
                },
                {
                    default: () => [
                        h(statusIcon(status), { class: 'size-3.5' }),
                        __(status),
                    ],
                },
            );
        },
        meta: {
            label: 'Status',
        },
    }),
    columnHelper.accessor('records', {
        header: __('Records'),
        cell: ({ getValue }) =>
            h(
                'span',
                { class: 'font-medium tabular-nums' },
                String(getValue()),
            ),
        meta: {
            label: 'Records',
        },
    }),
    columnHelper.accessor('health_score', {
        header: __('Health score'),
        cell: ({ getValue }) =>
            h('span', { class: 'font-medium tabular-nums' }, `${getValue()}%`),
        meta: {
            label: 'Health score',
        },
    }),
    columnHelper.accessor('updated_at', {
        header: __('Updated'),
        cell: ({ getValue }) =>
            h(
                'span',
                { class: 'whitespace-nowrap text-muted-foreground' },
                dateFormatter.value.format(new Date(String(getValue()))),
            ),
        meta: {
            label: 'Updated',
        },
    }),
]);
</script>

<template>
    <Head :title="__('Dashboard')" />

    <AppLayout :breadcrumbs="dashboardBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
            >
                <Card
                    class="border-sidebar-border/70 bg-sidebar/25 shadow-none"
                >
                    <CardContent class="space-y-4 px-5 py-5 md:px-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class="rounded-full">
                                {{ __('Tyanc admin') }}
                            </Badge>
                            <Badge variant="outline" class="rounded-full">
                                {{ __('Phase 4') }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                {{ __('Operations overview') }}
                            </h1>
                            <p
                                class="max-w-2xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    __(
                                        'Track the admin shell rollout across the Tyanc workspace before deeper modules arrive.',
                                    )
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
                    <Card
                        v-for="item in summaryCards"
                        :key="item.title"
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                    >
                        <CardHeader
                            class="flex flex-row items-start justify-between space-y-0 pb-2"
                        >
                            <CardTitle
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ item.title }}
                            </CardTitle>
                            <component
                                :is="item.icon"
                                class="size-4 text-muted-foreground"
                            />
                        </CardHeader>
                        <CardContent class="space-y-1">
                            <p
                                class="text-2xl font-semibold tracking-tight text-foreground"
                            >
                                {{ item.value }}
                            </p>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section class="space-y-3">
                <div class="space-y-1 px-1">
                    <h2 class="text-lg font-semibold text-foreground">
                        {{ __('Workspace readiness') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Sorting, filters, pagination, and column visibility stay in sync with the dashboard URL.',
                            )
                        }}
                    </p>
                </div>

                <DataTable
                    :columns="columns"
                    :rows="props.modulesTable.rows"
                    :meta="props.modulesTable.meta"
                    :query="props.modulesTable.query"
                    :filters="props.modulesTable.filters"
                    :route="dashboard"
                    :only="['summary', 'modulesTable']"
                    :empty-title="
                        __('No dashboard modules match the current filters.')
                    "
                    :empty-description="
                        __('Try a different team, status, or module search.')
                    "
                />
            </section>
        </div>
    </AppLayout>
</template>
