<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, inject, ref, watch } from 'vue';
import type { ComputedRef } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{ item: NavItem }>();

const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

/**
 * All hrefs in the nav tree, provided by NavMain.
 * Used to implement best-match-wins active state: a less-specific leaf
 * will NOT be highlighted when a more-specific sibling also matches
 * the current URL via prefix.
 */
const allNavHrefs = inject<ComputedRef<string[]>>(
    'navAllHrefs',
    computed(() => []),
);

const hasChildren = computed(() => !!props.item.children?.length);
const hasActiveChild = computed(
    () =>
        props.item.children?.some(
            (child) => child.href && isCurrentOrParentUrl(child.href),
        ) ?? false,
);
const isOpen = ref(hasChildren.value && hasActiveChild.value);

/**
 * Active state for leaf (no-children) items.
 *
 * Uses prefix matching so detail pages (e.g. /users/123) keep their
 * parent item highlighted, but yields to any sibling whose href is a
 * longer (more-specific) prefix match — preventing e.g. "Approval inbox"
 * from staying lit on the "Reports" page.
 */
const isLeafActive = computed(() => {
    const href = props.item.href;
    if (!href) return false;
    if (!isCurrentOrParentUrl(href)) return false;

    const hrefStr = href as string;
    const hasMoreSpecificMatch = allNavHrefs.value
        .filter((h) => h !== hrefStr)
        .some((h) => isCurrentOrParentUrl(h) && h.length > hrefStr.length);

    return !hasMoreSpecificMatch;
});

watch(currentUrl, () => {
    if (hasActiveChild.value) {
        isOpen.value = true;
    }
});
</script>

<template>
    <SidebarMenuItem>
        <!-- ─── Parent group: collapsible ─── -->
        <Collapsible
            v-if="hasChildren"
            v-model:open="isOpen"
            class="group/collapsible"
        >
            <CollapsibleTrigger as-child>
                <SidebarMenuButton
                    :tooltip="item.title"
                    :is-active="hasActiveChild || isOpen"
                >
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ item.title }}</span>
                    <ChevronRight
                        class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                    />
                </SidebarMenuButton>
            </CollapsibleTrigger>

            <CollapsibleContent
                class="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down"
            >
                <SidebarMenuSub>
                    <SidebarMenuSubItem
                        v-for="child in item.children"
                        :key="child.href ? String(child.href) : child.title"
                    >
                        <SidebarMenuSubButton
                            as-child
                            :is-active="
                                child.href ? isCurrentUrl(child.href) : false
                            "
                        >
                            <Link v-if="child.href" :href="child.href">
                                <component :is="child.icon" v-if="child.icon" />
                                <span>{{ child.title }}</span>
                            </Link>
                            <!-- Parent-only child with no href (header label) -->
                            <span v-else>
                                <component :is="child.icon" v-if="child.icon" />
                                <span>{{ child.title }}</span>
                            </span>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </CollapsibleContent>
        </Collapsible>

        <!-- ─── Leaf item: direct link ─── -->
        <SidebarMenuButton
            v-else-if="item.href"
            as-child
            :is-active="isLeafActive"
            :tooltip="item.title"
        >
            <Link :href="item.href">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ item.title }}</span>
            </Link>
        </SidebarMenuButton>

        <!-- ─── Placeholder item: visible but not clickable ─── -->
        <SidebarMenuButton
            v-else
            :tooltip="item.title"
            class="cursor-default opacity-70"
        >
            <component :is="item.icon" v-if="item.icon" />
            <span>{{ item.title }}</span>
        </SidebarMenuButton>
    </SidebarMenuItem>
</template>
