<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    event: string | null;
}>();

const { __ } = useTranslations();

const config = computed(() => {
    switch (props.event) {
        case 'created':
            return {
                label: __('created'),
                class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            };
        case 'updated':
            return {
                label: __('updated'),
                class: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
            };
        case 'deleted':
            return {
                label: __('deleted'),
                class: 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
            };
        case 'login':
            return {
                label: __('login'),
                class: 'border-neutral-500/20 bg-neutral-500/10 text-neutral-700 dark:text-neutral-300',
            };
        default:
            return {
                label: props.event ? __(props.event) : __('Unknown'),
                class: 'border-muted-foreground/20 bg-muted/40 text-muted-foreground',
            };
    }
});
</script>

<template>
    <Badge variant="outline" :class="`rounded-full text-xs ${config.class}`">
        {{ config.label }}
    </Badge>
</template>
