<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    status: string;
}>();

const { __ } = useTranslations();

const config = computed(() => {
    switch (props.status) {
        case 'active':
            return {
                label: __('active'),
                class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            };
        case 'suspended':
            return {
                label: __('suspended'),
                class: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            };
        case 'banned':
            return {
                label: __('banned'),
                class: 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
            };
        case 'pending_verification':
            return {
                label: __('pending_verification'),
                class: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
            };
        default:
            return {
                label: __(props.status),
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
