<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import AppFormDialog from '@/components/tyanc/apps/AppFormDialog.vue';
import { createAppTableColumns } from '@/components/tyanc/apps/AppTableColumns';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { destroy, index } from '@/routes/tyanc/apps';
import type { AppData, AppRow, DataTablePayload } from '@/types';

const props = defineProps<{
    apps: AppData[];
    appsTable: DataTablePayload<AppRow>;
}>();

const { __ } = useTranslations();
const { appsBreadcrumbs } = useAppNavigation();

const breadcrumbs = appsBreadcrumbs;

const dialogOpen = ref(false);
const editingApp = ref<AppRow | null>(null);
const deletingAppKey = ref<string | null>(null);

function openCreate() {
    editingApp.value = null;
    dialogOpen.value = true;
}

function openEdit(app: AppRow) {
    editingApp.value = app;
    dialogOpen.value = true;
}

function handleDelete(app: AppRow) {
    if (deletingAppKey.value !== app.key) {
        deletingAppKey.value = app.key;
        return;
    }

    router.delete(destroy.url({ app: app.key }), {
        preserveScroll: true,
        onFinish: () => {
            deletingAppKey.value = null;
        },
    });
}

const columns = computed<ColumnDef<AppRow>[]>(() =>
    createAppTableColumns(openEdit, handleDelete),
);
</script>

<template>
    <Head :title="__('Apps')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Apps') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Manage registered applications in this workspace.',
                            )
                        }}
                    </p>
                </div>

                <Button size="sm" class="gap-2" @click="openCreate">
                    <PlusCircle class="size-4" />
                    {{ __('New app') }}
                </Button>
            </div>

            <!-- Table -->
            <DataTable
                :columns="columns"
                :rows="props.appsTable.rows"
                :meta="props.appsTable.meta"
                :query="props.appsTable.query"
                :filters="props.appsTable.filters"
                :route="index"
                :only="['appsTable']"
                :empty-title="__('No apps registered.')"
                :empty-description="
                    __('Register applications to surface them in navigation.')
                "
            />
        </div>
    </AppLayout>

    <AppFormDialog
        v-model:open="dialogOpen"
        :editing-app="editingApp"
        :apps="props.apps"
    />
</template>
