<script setup lang="ts">
import { BookOpen, FolderGit2 } from 'lucide-vue-next';
import AppSwitcher from '@/components/AppSwitcher.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import type { NavItem } from '@/types';

const { activeApp, mainNavItems } = useAppNavigation();

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <AppSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" :label="activeApp.title" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
