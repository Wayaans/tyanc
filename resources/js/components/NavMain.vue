<script setup lang="ts">
import NavMainItem from '@/components/NavMainItem.vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    {
        label: 'Platform',
    },
);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ props.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavMainItem
                v-for="item in items"
                :key="item.href ? String(item.href) : item.title"
                :item="item"
            />
        </SidebarMenu>
    </SidebarGroup>
</template>
