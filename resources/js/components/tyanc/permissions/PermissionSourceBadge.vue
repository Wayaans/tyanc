<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { PermissionSyncStatus } from '@/types';

const props = defineProps<{
    status: PermissionSyncStatus | null;
}>();

const { __ } = useTranslations();

const config = computed(() => {
    switch (props.status) {
        case 'synced':
            return {
                label: __('Synced'),
                class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            };
        case 'orphaned':
            return {
                label: __('Orphaned'),
                class: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            };
        case 'missing':
            return {
                label: __('Missing'),
                class: 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300',
            };
        default:
            return {
                label: __('Unknown'),
                class: 'border-muted/40 bg-muted/20 text-muted-foreground',
            };
    }
});
</script>

<template>
    <Badge variant="outline" :class="`rounded-full text-xs ${config.class}`">
        {{ config.label }}
    </Badge>
</template>
