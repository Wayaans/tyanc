<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ApprovalListTable from '@/components/cumpu/approvals/ApprovalListTable.vue';
import ApprovalOverviewFilters from '@/components/cumpu/approvals/ApprovalOverviewFilters.vue';
import ApprovalReassignDialog from '@/components/cumpu/approvals/ApprovalReassignDialog.vue';
import ApprovalRequestDrawer from '@/components/cumpu/approvals/ApprovalRequestDrawer.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { all, show } from '@/routes/cumpu/approvals';
import type {
    ApprovalRequestRow,
    DataTablePayload,
    SelectOption,
} from '@/types';
import type { ReassignOption } from '@/types/cumpu';

const props = defineProps<{
    approvalsTable: DataTablePayload<ApprovalRequestRow>;
    appOptions?: SelectOption[];
}>();

const { __ } = useTranslations();
const { cumpuAllApprovalsBreadcrumbs } = useAppNavigation();

const drawerOpen = ref(false);
const selectedRequest = ref<ApprovalRequestRow | null>(null);

const reassignDialogOpen = ref(false);
const reassignTarget = ref<ApprovalRequestRow | null>(null);
const resolvedReassignOptions = ref<ReassignOption[]>([]);

const initialFilters = computed(() => {
    const f = props.approvalsTable.query.filter ?? {};
    return {
        status: (f.status as string) ?? '',
        app_key: (f.app_key as string) ?? '',
        search: (f.search as string) ?? '',
        assignee: (f.assignee as string) ?? '',
        escalated: f.escalated === '1',
        reassigned: f.reassigned === '1',
        overdue: f.overdue === '1',
    };
});

function openDrawer(request: ApprovalRequestRow) {
    selectedRequest.value = request;
    drawerOpen.value = true;
}

function openDecisionDialog(request: ApprovalRequestRow) {
    openDrawer(request);
}

async function openReassignFromDrawer(request: ApprovalRequestRow) {
    reassignTarget.value = request;

    const response = await window.fetch(
        show.url({ approvalRequest: request.id }),
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        resolvedReassignOptions.value = [];
        reassignDialogOpen.value = false;

        return;
    }

    const payload = (await response.json()) as {
        reassignOptions?: ReassignOption[];
    };

    resolvedReassignOptions.value = payload.reassignOptions ?? [];
    reassignDialogOpen.value = resolvedReassignOptions.value.length > 0;
}
</script>

<template>
    <Head :title="__('All approvals')" />

    <AppLayout :breadcrumbs="cumpuAllApprovalsBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('All approvals') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Full view of approval requests across all apps, workflows, and users.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <ApprovalOverviewFilters
                :route="all"
                :only="['approvalsTable']"
                :app-options="props.appOptions"
                :initial="initialFilters"
            />

            <!-- Table -->
            <ApprovalListTable
                :approvals-table="props.approvalsTable"
                :route="all"
                :only="['approvalsTable']"
                :show-detail-link="true"
                :empty-title="__('No approval requests found.')"
                :empty-description="
                    __('Try adjusting your filters or check back later.')
                "
                @decide="openDecisionDialog"
            />
        </div>
    </AppLayout>

    <!-- Detail drawer -->
    <ApprovalRequestDrawer
        v-model:open="drawerOpen"
        :request="selectedRequest"
        @reassign="openReassignFromDrawer"
    />

    <!-- Reassign dialog -->
    <ApprovalReassignDialog
        v-model:open="reassignDialogOpen"
        :request="reassignTarget"
        :reassign-options="resolvedReassignOptions"
    />
</template>
