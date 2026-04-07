<script setup lang="ts">
import AppHeaderActions from '@/components/AppHeaderActions.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
            <SidebarTrigger class="-ml-1" />
            <Separator orientation="vertical" class="h-4" />
            <div v-if="breadcrumbs.length > 0" class="min-w-0 overflow-x-auto">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>

        <AppHeaderActions />
    </header>
</template>
