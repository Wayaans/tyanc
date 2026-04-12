<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { computed, ref } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import ApprovalHistoryPanel from '@/components/cumpu/approvals/ApprovalHistoryPanel.vue';
import ApprovalRequestBanner from '@/components/cumpu/approvals/ApprovalRequestBanner.vue';
import { createActivityTableColumns } from '@/components/tyanc/activity/ActivityTableColumns';
import ApprovalDecisionDialog from '@/components/tyanc/approvals/ApprovalDecisionDialog.vue';
import ApprovalTimeline from '@/components/tyanc/approvals/ApprovalTimeline.vue';
import ExportMenu from '@/components/tyanc/exports/ExportMenu.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { exportMethod, index } from '@/routes/tyanc/activity-log';
import { pdf } from '@/routes/tyanc/activity-log/export';
import type {
    ActivityRow,
    ApprovalRequestRow,
    DataTablePayload,
} from '@/types';
import type { ApprovalContext } from '@/types/cumpu';

const props = defineProps<{
    activitiesTable: DataTablePayload<ActivityRow>;
    approvalRequests: ApprovalRequestRow[];
    abilities: {
        export: boolean;
        reviewApprovals: boolean;
    };
    features: {
        exports_enabled: boolean;
    };
    approvalContext?: ApprovalContext | null;
}>();

const { __, locale } = useTranslations();
const { activityLogBreadcrumbs } = useAppNavigation();

const breadcrumbs = activityLogBreadcrumbs;

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const columns = computed<ColumnDef<ActivityRow>[]>(() =>
    createActivityTableColumns(dateFormatter.value),
);

const decisionDialogOpen = ref(false);
const selectedApproval = ref<ApprovalRequestRow | null>(null);

const exportOptions = computed(() => [
    {
        label: __('Download spreadsheet'),
        url: exportMethod.url(),
        description: __('Export activity log'),
    },
    {
        label: __('Download PDF'),
        url: pdf.url(),
        description: __('Export activity log'),
    },
]);

function openDecisionDialog(request: ApprovalRequestRow) {
    selectedApproval.value = request;
    decisionDialogOpen.value = true;
}
</script>

<template>
    <Head :title="__('Activity log')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Activity log') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Review all system and user activity across the application.',
                            )
                        }}
                    </p>
                </div>

                <ExportMenu
                    :options="exportOptions"
                    :disabled="
                        !props.features.exports_enabled ||
                        !props.abilities.export
                    "
                />
            </div>

            <!-- Approval banner -->
            <ApprovalRequestBanner
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />

            <!-- Approval timeline (reviewers only) -->
            <ApprovalTimeline
                v-if="
                    props.abilities.reviewApprovals &&
                    props.approvalRequests.length > 0
                "
                :approval-requests="props.approvalRequests"
                @decide="openDecisionDialog"
            />

            <!-- Table -->
            <DataTable
                :columns="columns"
                :rows="props.activitiesTable.rows"
                :meta="props.activitiesTable.meta"
                :query="props.activitiesTable.query"
                :filters="props.activitiesTable.filters"
                :route="index"
                :only="['activitiesTable']"
                :empty-title="__('No activity found.')"
                :empty-description="
                    __(
                        'No events have been logged yet, or your filters returned no results.',
                    )
                "
            />
            <!-- Approval history -->
            <ApprovalHistoryPanel
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />
        </div>
    </AppLayout>

    <!-- Approval decision dialog -->
    <ApprovalDecisionDialog
        v-model:open="decisionDialogOpen"
        :request="selectedApproval"
    />
</template>
