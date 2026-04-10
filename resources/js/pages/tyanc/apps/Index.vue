<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { PlusCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import { createAppTableColumns } from '@/components/tyanc/apps/AppTableColumns';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { create, index } from '@/routes/tyanc/apps';
import type { AppRow, DataTablePayload } from '@/types';

const props = defineProps<{
    appsTable: DataTablePayload<AppRow>;
}>();

const { __ } = useTranslations();
const { appsBreadcrumbs } = useAppNavigation();

const breadcrumbs = appsBreadcrumbs;

const columns = computed<ColumnDef<AppRow>[]>(() => createAppTableColumns());

function goToCreate() {
    router.visit(create.url());
}
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

                <Button size="sm" class="gap-2" @click="goToCreate">
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
</template>
