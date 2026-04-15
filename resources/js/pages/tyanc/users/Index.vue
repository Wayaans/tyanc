<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { CheckCircle2, PlusCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import ApprovalDecisionDialog from '@/components/tyanc/approvals/ApprovalDecisionDialog.vue';
import ApprovalTimeline from '@/components/tyanc/approvals/ApprovalTimeline.vue';
import ExportMenu from '@/components/tyanc/exports/ExportMenu.vue';
import { createUserTableColumns } from '@/components/tyanc/users/UserTableColumns';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { create, exportMethod, index } from '@/routes/tyanc/users';
import { pdf } from '@/routes/tyanc/users/export';
import type { ApprovalRequestRow, DataTablePayload, UserRow } from '@/types';

const props = defineProps<{
    usersTable: DataTablePayload<UserRow>;
    approvalRequests: ApprovalRequestRow[];
    abilities: {
        export: boolean;
        reviewApprovals: boolean;
    };
    features: {
        exports_enabled: boolean;
    };
    status?: string | null;
}>();

const { __, locale } = useTranslations();
const { usersBreadcrumbs } = useAppNavigation();

const breadcrumbs = usersBreadcrumbs;

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const columns = computed<ColumnDef<UserRow>[]>(() =>
    createUserTableColumns(dateFormatter.value),
);

const decisionDialogOpen = ref(false);
const selectedApproval = ref<ApprovalRequestRow | null>(null);

const exportOptions = computed(() => [
    {
        label: __('Download spreadsheet'),
        url: exportMethod.url(),
        description: __('Export users'),
    },
    {
        label: __('Download PDF'),
        url: pdf.url(),
        description: __('Export users'),
    },
]);

function goToCreate() {
    router.visit(create.url());
}

function openDecisionDialog(request: ApprovalRequestRow) {
    selectedApproval.value = request;
    decisionDialogOpen.value = true;
}
</script>

<template>
    <Head :title="__('Users')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Users') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ __('Manage user accounts, roles, and access.') }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <ExportMenu
                        :options="exportOptions"
                        :disabled="
                            !props.features.exports_enabled ||
                            !props.abilities.export
                        "
                    />

                    <Button size="sm" class="gap-2" @click="goToCreate">
                        <PlusCircle class="size-4" />
                        {{ __('New user') }}
                    </Button>
                </div>
            </div>

            <!-- Status feedback -->
            <div
                v-if="props.status"
                class="flex items-start gap-3 rounded-xl border border-emerald-200/60 bg-emerald-50/50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/[0.07]"
            >
                <CheckCircle2
                    class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
                <p class="text-sm text-emerald-800 dark:text-emerald-200">
                    {{ props.status }}
                </p>
            </div>

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
                :rows="props.usersTable.rows"
                :meta="props.usersTable.meta"
                :query="props.usersTable.query"
                :filters="props.usersTable.filters"
                :route="index"
                :only="['usersTable']"
                :empty-title="__('No users found.')"
                :empty-description="
                    __('Try adjusting your filters or create a new user.')
                "
            />
        </div>
    </AppLayout>

    <!-- Approval decision dialog -->
    <ApprovalDecisionDialog
        v-model:open="decisionDialogOpen"
        :request="selectedApproval"
    />
</template>
