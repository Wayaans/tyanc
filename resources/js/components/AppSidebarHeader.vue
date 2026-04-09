<script setup lang="ts">
import AppHeaderActions from '@/components/AppHeaderActions.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { activeApp } = useAppNavigation();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
            <SidebarTrigger class="-ml-1" />
            <Separator orientation="vertical" class="h-4" />
            <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                <span
                    class="inline-flex max-w-24 shrink-0 truncate rounded-full border border-sidebar-border/70 bg-sidebar px-2.5 py-1 text-[11px] font-medium tracking-[0.18em] text-sidebar-foreground/70 uppercase sm:hidden"
                >
                    {{ activeApp.title }}
                </span>
                <div
                    v-if="props.breadcrumbs.length > 0"
                    class="min-w-0 overflow-hidden"
                >
                    <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
                </div>
            </div>
        </div>

        <AppHeaderActions class="shrink-0" />
    </header>
</template>
