<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ApprovalDecisionDialog from '@/components/cumpu/approvals/ApprovalDecisionDialog.vue';
import ApprovalListTable from '@/components/cumpu/approvals/ApprovalListTable.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index } from '@/routes/cumpu/approvals';
import type { ApprovalRequestRow, DataTablePayload } from '@/types';

const props = defineProps<{
    approvalsTable: DataTablePayload<ApprovalRequestRow>;
}>();

const { __ } = useTranslations();
const { cumpuInboxBreadcrumbs } = useAppNavigation();

const decisionDialogOpen = ref(false);
const selectedApproval = ref<ApprovalRequestRow | null>(null);

function openDecisionDialog(request: ApprovalRequestRow) {
    selectedApproval.value = request;
    decisionDialogOpen.value = true;
}
</script>

<template>
    <Head :title="__('Approvals inbox')" />

    <AppLayout :breadcrumbs="cumpuInboxBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Approvals inbox') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Review and decide on pending approval requests assigned to you.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Table -->
            <ApprovalListTable
                :approvals-table="props.approvalsTable"
                :route="index"
                :only="['approvalsTable']"
                :show-detail-link="true"
                :empty-title="__('No pending requests.')"
                :empty-description="
                    __('You have no approval requests waiting for your review.')
                "
                @decide="openDecisionDialog"
            />
        </div>
    </AppLayout>

    <!-- Decision dialog -->
    <ApprovalDecisionDialog
        v-model:open="decisionDialogOpen"
        :request="selectedApproval"
    />
</template>
