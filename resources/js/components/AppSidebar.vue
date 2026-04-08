<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSwitcher from '@/components/AppSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';

const page = usePage();
const { activeApp, mainNavItems } = useAppNavigation();

const sidebarVariant = computed(() => {
    const variant = page.props.theme?.sidebar_variant;

    return variant === 'floating' ||
        variant === 'sidebar' ||
        variant === 'inset'
        ? variant
        : 'inset';
});
</script>

<template>
    <Sidebar collapsible="icon" :variant="sidebarVariant">
        <SidebarHeader>
            <AppSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" :label="activeApp.title" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
