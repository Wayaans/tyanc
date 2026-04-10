<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { computed } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import PermissionCatalogFilters from '@/components/tyanc/permissions/PermissionCatalogFilters.vue';
import PermissionSyncStatusCard from '@/components/tyanc/permissions/PermissionSyncStatusCard.vue';
import { createPermissionTableColumns } from '@/components/tyanc/permissions/PermissionTableColumns';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index } from '@/routes/tyanc/permissions';
import type {
    PermissionOptions,
    PermissionRow,
    PermissionsTablePayload,
    SelectOption,
} from '@/types';

type CatalogFilters = {
    app: string;
    status: string;
};

const props = defineProps<{
    permissionsTable: PermissionsTablePayload;
    permissionOptions: PermissionOptions;
    canSyncPermissions: boolean;
}>();

const { __ } = useTranslations();
const { permissionsBreadcrumbs } = useAppNavigation();

const breadcrumbs = permissionsBreadcrumbs;

const appOptions = computed<SelectOption[]>(() => props.permissionOptions.apps);

const activeFilters = computed<CatalogFilters>(() => ({
    app: (props.permissionsTable.query.filter['app'] as string) ?? '',
    status:
        (props.permissionsTable.query.filter['status'] as string) ??
        (props.permissionsTable.query.filter['sync_status'] as string) ??
        '',
}));

function applyFilters(filters: CatalogFilters) {
    router.get(
        index.url(),
        {
            filter: {
                ...props.permissionsTable.query.filter,
                app: filters.app,
                status: filters.status,
                sync_status: filters.status,
            },
        },
        { preserveScroll: true, only: ['permissionsTable'] },
    );
}

const columns = computed<ColumnDef<PermissionRow>[]>(() =>
    createPermissionTableColumns(),
);
</script>

<template>
    <Head :title="__('Permissions')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Permissions') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Permission catalog — managed by the system. Use roles to assign access.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Sync status card -->
            <PermissionSyncStatusCard
                :summary="props.permissionsTable.summary"
                :can-sync="props.canSyncPermissions"
            />

            <!-- Catalog filters -->
            <PermissionCatalogFilters
                :model-value="activeFilters"
                :apps="appOptions"
                @update:model-value="applyFilters"
            />

            <!-- Table -->
            <DataTable
                :columns="columns"
                :rows="props.permissionsTable.rows"
                :meta="props.permissionsTable.meta"
                :query="props.permissionsTable.query"
                :filters="props.permissionsTable.filters"
                :route="index"
                :only="['permissionsTable']"
                :empty-title="__('No permissions found.')"
                :empty-description="
                    __(
                        'Permissions are auto-discovered. Run a sync to populate the catalog.',
                    )
                "
            />
        </div>
    </AppLayout>
</template>
