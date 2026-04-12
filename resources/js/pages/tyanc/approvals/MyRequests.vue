<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ApprovalDecisionDialog from '@/components/tyanc/approvals/ApprovalDecisionDialog.vue';
import ApprovalListTable from '@/components/tyanc/approvals/ApprovalListTable.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { myRequests } from '@/routes/tyanc/approvals';
import type { ApprovalRequestRow, DataTablePayload } from '@/types';

const props = defineProps<{
    approvalsTable: DataTablePayload<ApprovalRequestRow>;
}>();

const { __ } = useTranslations();
const { myRequestsBreadcrumbs } = useAppNavigation();

const decisionDialogOpen = ref(false);
const selectedApproval = ref<ApprovalRequestRow | null>(null);

function openDecisionDialog(request: ApprovalRequestRow) {
    selectedApproval.value = request;
    decisionDialogOpen.value = true;
}
</script>

<template>
    <Head :title="__('My requests')" />

    <AppLayout :breadcrumbs="myRequestsBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('My requests') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Track your submitted approval requests and cancel pending ones.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Table -->
            <ApprovalListTable
                :approvals-table="props.approvalsTable"
                :route="myRequests"
                :only="['approvalsTable']"
                :empty-title="__('No requests yet.')"
                :empty-description="
                    __('You have not submitted any approval requests.')
                "
                @decide="openDecisionDialog"
            />
        </div>
    </AppLayout>

    <!-- Decision dialog (view + cancel) -->
    <ApprovalDecisionDialog
        v-model:open="decisionDialogOpen"
        :request="selectedApproval"
    />
</template>
