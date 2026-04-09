<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { computed } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import { createActivityTableColumns } from '@/components/tyanc/activity/ActivityTableColumns';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index } from '@/routes/tyanc/activity-log';
import type { ActivityRow, DataTablePayload } from '@/types';

const props = defineProps<{
    activitiesTable: DataTablePayload<ActivityRow>;
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
</script>

<template>
    <Head :title="__('Activity log')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
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
        </div>
    </AppLayout>
</template>
