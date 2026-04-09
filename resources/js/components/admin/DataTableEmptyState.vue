<script setup lang="ts">
import { SearchX } from 'lucide-vue-next';
import { computed } from 'vue';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    title?: string;
    description?: string;
}>();

const { __ } = useTranslations();

const title = computed(() => props.title ?? __('No matching rows'));
const description = computed(
    () =>
        props.description ??
        __('Adjust your filters or column visibility to see more results.'),
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-sidebar-border/70 bg-sidebar/20 px-6 py-14 text-center"
    >
        <div
            class="flex size-11 items-center justify-center rounded-full border border-sidebar-border/70 bg-background text-muted-foreground"
        >
            <SearchX class="size-5" />
        </div>
        <div class="space-y-1.5">
            <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
            <p class="max-w-md text-sm leading-6 text-muted-foreground">
                {{ description }}
            </p>
        </div>
    </div>
</template>
