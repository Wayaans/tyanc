<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import ApprovalRuleFormDialog from '@/components/cumpu/approval-rules/ApprovalRuleFormDialog.vue';
import ApprovalRuleTable from '@/components/cumpu/approval-rules/ApprovalRuleTable.vue';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import type { SelectOption } from '@/types';
import type { ApprovalRule, RoleOption } from '@/types/cumpu';

type ActionOption = SelectOption & { permission: string };

type PermissionOptions = {
    apps: SelectOption[];
    resources: Record<string, SelectOption[]>;
    actions: Record<string, Record<string, ActionOption[]>>;
};

const props = defineProps<{
    rules: ApprovalRule[];
    permissionOptions: PermissionOptions;
    roles: RoleOption[];
}>();

const { __ } = useTranslations();
const { cumpuApprovalRulesBreadcrumbs } = useAppNavigation();

const formDialogOpen = ref(false);
const editingRule = ref<ApprovalRule | null>(null);

function openCreateDialog() {
    editingRule.value = null;
    formDialogOpen.value = true;
}

function openEditDialog(rule: ApprovalRule) {
    editingRule.value = rule;
    formDialogOpen.value = true;
}
</script>

<template>
    <Head :title="__('Approval rules')" />

    <AppLayout :breadcrumbs="cumpuApprovalRulesBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Approval rules') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Define which actions require sign-off before taking effect and who reviews them.',
                            )
                        }}
                    </p>
                </div>

                <Button size="sm" @click="openCreateDialog">
                    <Plus class="mr-1.5 size-3.5" />
                    {{ __('New rule') }}
                </Button>
            </div>

            <!-- Rules table -->
            <ApprovalRuleTable :rules="props.rules" @edit="openEditDialog" />
        </div>
    </AppLayout>

    <!-- Create / edit dialog -->
    <ApprovalRuleFormDialog
        :open="formDialogOpen"
        :editing-rule="editingRule"
        :permission-options="props.permissionOptions"
        :roles="props.roles"
        @update:open="formDialogOpen = $event"
    />
</template>
