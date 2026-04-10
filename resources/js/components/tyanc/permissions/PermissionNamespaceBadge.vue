<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    namespace: string | null;
}>();

const { __ } = useTranslations();

const colorMap: Record<string, string> = {
    tyanc: 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    erp: 'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    tasks: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
};

const badgeClass = computed(() => {
    if (!props.namespace) {
        return 'border-muted/40 bg-muted/20 text-muted-foreground';
    }
    return (
        colorMap[props.namespace.toLowerCase()] ??
        'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300'
    );
});
</script>

<template>
    <Badge
        variant="outline"
        :class="`rounded-full font-mono text-xs ${badgeClass}`"
    >
        {{ props.namespace ?? __('global') }}
    </Badge>
</template>
