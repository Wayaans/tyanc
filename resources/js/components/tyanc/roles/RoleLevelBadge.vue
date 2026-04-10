<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    level: number;
}>();

const { __ } = useTranslations();

const config = computed(() => {
    if (props.level <= 10) {
        return {
            class: 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300',
        };
    }

    if (props.level <= 50) {
        return {
            class: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        };
    }

    if (props.level <= 90) {
        return {
            class: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        };
    }

    return {
        class: 'border-muted/40 bg-muted/20 text-muted-foreground',
    };
});
</script>

<template>
    <Badge
        variant="outline"
        :class="`rounded-full text-xs tabular-nums ${config.class}`"
    >
        {{ __('Level :n', { n: String(props.level) }) }}
    </Badge>
</template>
