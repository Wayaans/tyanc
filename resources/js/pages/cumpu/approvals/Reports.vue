<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Filter, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalReportSummaryCards from '@/components/cumpu/approvals/reports/ApprovalReportSummaryCards.vue';
import ApprovalReportTable from '@/components/cumpu/approvals/reports/ApprovalReportTable.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import {
    index as reportsRoute,
    exportMethod,
} from '@/routes/cumpu/approvals/reports';
import type {
    ApprovalReportRow,
    ApprovalReportSummary,
    DataTableMeta,
    DataTableQuery,
    SelectOption,
} from '@/types';

type ReportFilters = {
    date_from: string;
    date_to: string;
    status: string;
    app_key: string;
    escalated: boolean;
    reassigned: boolean;
    overdue: boolean;
};

const props = defineProps<{
    rows: ApprovalReportRow[];
    meta: DataTableMeta;
    query: DataTableQuery;
    filters: ReportFilters;
    summary: ApprovalReportSummary;
    appOptions?: SelectOption[];
}>();

const { __ } = useTranslations();
const { cumpuApprovalReportsBreadcrumbs } = useAppNavigation();

const localFilters = ref<ReportFilters>({
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    status: props.filters?.status ?? '',
    app_key: props.filters?.app_key ?? '',
    escalated: props.filters?.escalated ?? false,
    reassigned: props.filters?.reassigned ?? false,
    overdue: props.filters?.overdue ?? false,
});

const isDirty = computed(() =>
    Object.values(localFilters.value).some((v) => Boolean(v)),
);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function buildQueryParams(): Record<string, string> {
    const q: Record<string, string> = {};
    if (localFilters.value.date_from) {
        q['filter[date_from]'] = localFilters.value.date_from;
    }
    if (localFilters.value.date_to) {
        q['filter[date_to]'] = localFilters.value.date_to;
    }
    if (localFilters.value.status) {
        q['filter[status]'] = localFilters.value.status;
    }
    if (localFilters.value.app_key) {
        q['filter[app_key]'] = localFilters.value.app_key;
    }
    if (localFilters.value.escalated) {
        q['filter[escalated]'] = '1';
    }
    if (localFilters.value.reassigned) {
        q['filter[reassigned]'] = '1';
    }
    if (localFilters.value.overdue) {
        q['filter[overdue]'] = '1';
    }
    return q;
}

function clearPendingDateApply() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
}

function applyFilters(page = 1) {
    clearPendingDateApply();

    const query = buildQueryParams();
    if (page > 1) {
        query['page'] = String(page);
    }
    router.get(
        reportsRoute({ query }).url,
        {},
        {
            preserveScroll: true,
            only: ['rows', 'meta', 'query', 'filters', 'summary'],
        },
    );
}

watch(
    () => [
        localFilters.value.status,
        localFilters.value.app_key,
        localFilters.value.escalated,
        localFilters.value.reassigned,
        localFilters.value.overdue,
    ],
    () => applyFilters(),
);

watch(
    () => [localFilters.value.date_from, localFilters.value.date_to],
    () => {
        clearPendingDateApply();
        searchTimeout = setTimeout(() => applyFilters(), 400);
    },
);

function resetFilters() {
    localFilters.value = {
        date_from: '',
        date_to: '',
        status: '',
        app_key: '',
        escalated: false,
        reassigned: false,
        overdue: false,
    };
    applyFilters();
}

function handlePageChange(page: number) {
    applyFilters(page);
}

const exportUrl = computed(() => {
    const q = buildQueryParams();
    return exportMethod({ query: q as Record<string, string> }).url;
});
</script>

<template>
    <Head :title="__('Approval reports')" />

    <AppLayout :breadcrumbs="cumpuApprovalReportsBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Approval reports') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Analyse approval requests across all workflows. Filter and export to Excel.',
                            )
                        }}
                    </p>
                </div>
                <Button size="sm" variant="outline" as-child>
                    <a :href="exportUrl" download>
                        <Download class="mr-1.5 size-3.5" />
                        {{ __('Export Excel') }}
                    </a>
                </Button>
            </div>

            <!-- Summary cards -->
            <ApprovalReportSummaryCards :summary="props.summary" />

            <!-- Filters bar -->
            <div
                class="flex flex-wrap items-center gap-3 rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3"
            >
                <div class="flex items-center gap-1.5 text-muted-foreground">
                    <Filter class="size-3.5" />
                    <span class="text-xs font-medium">{{ __('Filters') }}</span>
                </div>

                <!-- Date from/to -->
                <div class="flex items-center gap-2">
                    <DatePickerField
                        :model-value="localFilters.date_from || null"
                        class="h-9 w-40"
                        @update:model-value="
                            localFilters.date_from = $event ?? ''
                        "
                    />
                    <span class="text-xs text-muted-foreground">–</span>
                    <DatePickerField
                        :model-value="localFilters.date_to || null"
                        class="h-9 w-40"
                        @update:model-value="
                            localFilters.date_to = $event ?? ''
                        "
                    />
                </div>

                <!-- Status -->
                <div class="w-36">
                    <Select
                        :model-value="localFilters.status"
                        @update:model-value="
                            localFilters.status =
                                $event === '_all' ? '' : String($event)
                        "
                    >
                        <SelectTrigger class="h-9 text-sm">
                            <SelectValue :placeholder="__('Status')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="_all">{{
                                __('All')
                            }}</SelectItem>
                            <SelectItem value="pending">{{
                                __('Pending')
                            }}</SelectItem>
                            <SelectItem value="in_review">{{
                                __('In review')
                            }}</SelectItem>
                            <SelectItem value="approved">{{
                                __('Approved')
                            }}</SelectItem>
                            <SelectItem value="rejected">{{
                                __('Rejected')
                            }}</SelectItem>
                            <SelectItem value="cancelled">{{
                                __('Cancelled')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- App -->
                <div
                    v-if="props.appOptions && props.appOptions.length > 0"
                    class="w-36"
                >
                    <Select
                        :model-value="localFilters.app_key"
                        @update:model-value="
                            localFilters.app_key =
                                $event === '_all' ? '' : String($event)
                        "
                    >
                        <SelectTrigger class="h-9 text-sm">
                            <SelectValue :placeholder="__('App')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="_all">{{
                                __('All apps')
                            }}</SelectItem>
                            <SelectItem
                                v-for="app in props.appOptions"
                                :key="app.value"
                                :value="app.value"
                            >
                                {{ app.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Flags -->
                <div class="flex items-center gap-4">
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <Checkbox
                            :model-value="localFilters.overdue"
                            @update:model-value="
                                localFilters.overdue = Boolean($event)
                            "
                        />
                        <span class="text-xs text-muted-foreground">{{
                            __('Overdue')
                        }}</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <Checkbox
                            :model-value="localFilters.escalated"
                            @update:model-value="
                                localFilters.escalated = Boolean($event)
                            "
                        />
                        <span class="text-xs text-muted-foreground">{{
                            __('Escalated')
                        }}</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <Checkbox
                            :model-value="localFilters.reassigned"
                            @update:model-value="
                                localFilters.reassigned = Boolean($event)
                            "
                        />
                        <span class="text-xs text-muted-foreground">{{
                            __('Reassigned')
                        }}</span>
                    </label>
                </div>

                <Button
                    v-if="isDirty"
                    variant="ghost"
                    size="sm"
                    class="h-9 gap-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <X class="size-3" />
                    {{ __('Clear') }}
                </Button>
            </div>

            <!-- Report table -->
            <ApprovalReportTable
                :rows="props.rows"
                :meta="props.meta"
                :query="props.query"
                @page-change="handlePageChange"
            />
        </div>
    </AppLayout>
</template>
