<script setup lang="ts">
import { computed, provide } from 'vue';
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
    {},
);

/** Collect every href in the nav tree (depth-first) for sibling-context injection. */
function collectHrefs(items: NavItem[]): string[] {
    return items.flatMap((item) => [
        ...(item.href ? [item.href as string] : []),
        ...(item.children ? collectHrefs(item.children) : []),
    ]);
}

const allNavHrefs = computed(() => collectHrefs(props.items));

/**
 * Injected by NavMainItem to implement best-match-wins active logic:
 * a less-specific leaf won't be marked active when a more-specific sibling also matches.
 */
provide('navAllHrefs', allNavHrefs);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="props.label">{{
            props.label
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <NavMainItem
                v-for="item in items"
                :key="item.href ? String(item.href) : item.title"
                :item="item"
            />
        </SidebarMenu>
    </SidebarGroup>
</template>
