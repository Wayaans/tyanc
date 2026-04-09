<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { PlusCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import { createUserTableColumns } from '@/components/tyanc/users/UserTableColumns';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { create, index } from '@/routes/tyanc/users';
import type { DataTablePayload, UserRow } from '@/types';

const props = defineProps<{
    usersTable: DataTablePayload<UserRow>;
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

function goToCreate() {
    router.visit(create.url());
}
</script>

<template>
    <Head :title="__('Users')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
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

                <Button size="sm" class="gap-2" @click="goToCreate">
                    <PlusCircle class="size-4" />
                    {{ __('New user') }}
                </Button>
            </div>

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
</template>
