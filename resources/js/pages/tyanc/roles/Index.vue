<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import RoleFormDialog from '@/components/tyanc/roles/RoleFormDialog.vue';
import RolePermissionAssignDialog from '@/components/tyanc/roles/RolePermissionAssignDialog.vue';
import { createRoleTableColumns } from '@/components/tyanc/roles/RoleTableColumns';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { destroy, index } from '@/routes/tyanc/roles';
import type { DataTablePayload, PermissionOptions, RoleRow } from '@/types';

const props = defineProps<{
    rolesTable: DataTablePayload<RoleRow>;
    permissionOptions: PermissionOptions;
}>();

const { __ } = useTranslations();
const { rolesBreadcrumbs } = useAppNavigation();

const breadcrumbs = rolesBreadcrumbs;

const dialogOpen = ref(false);
const editingRole = ref<RoleRow | null>(null);
const deletingId = ref<number | null>(null);

const assignDialogOpen = ref(false);
const assigningRole = ref<RoleRow | null>(null);

function openCreate() {
    editingRole.value = null;
    dialogOpen.value = true;
}

function openEdit(role: RoleRow) {
    editingRole.value = role;
    dialogOpen.value = true;
}

function openAssignPermissions(role: RoleRow) {
    assigningRole.value = role;
    assignDialogOpen.value = true;
}

function handleDelete(role: RoleRow) {
    if (deletingId.value === role.id) {
        router.delete(destroy.url({ role: role.id }), {
            preserveScroll: true,
            onFinish: () => {
                deletingId.value = null;
            },
        });
    } else {
        deletingId.value = role.id;
    }
}

const columns = computed<ColumnDef<RoleRow>[]>(() =>
    createRoleTableColumns(openEdit, openAssignPermissions, handleDelete),
);
</script>

<template>
    <Head :title="__('Roles')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Roles') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Manage role metadata first, then assign permissions from a dedicated workflow.',
                            )
                        }}
                    </p>
                </div>

                <Button size="sm" class="gap-2" @click="openCreate">
                    <PlusCircle class="size-4" />
                    {{ __('New role') }}
                </Button>
            </div>

            <!-- Table -->
            <DataTable
                :columns="columns"
                :rows="props.rolesTable.rows"
                :meta="props.rolesTable.meta"
                :query="props.rolesTable.query"
                :filters="props.rolesTable.filters"
                :route="index"
                :only="['rolesTable']"
                :empty-title="__('No roles found.')"
                :empty-description="
                    __('Create a role to start assigning permissions.')
                "
            />
        </div>
    </AppLayout>

    <RoleFormDialog v-model:open="dialogOpen" :editing-role="editingRole" />

    <RolePermissionAssignDialog
        v-model:open="assignDialogOpen"
        :role="assigningRole"
        :permission-options="props.permissionOptions"
    />
</template>
