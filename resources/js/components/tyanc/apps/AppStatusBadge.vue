<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    enabled: boolean;
    isSystem?: boolean;
}>();

const { __ } = useTranslations();

const statusConfig = computed(() => {
    if (props.enabled) {
        return {
            label: __('Enabled'),
            class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        };
    }

    return {
        label: __('Disabled'),
        class: 'border-muted/40 bg-muted/20 text-muted-foreground',
    };
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <Badge
            variant="outline"
            :class="`rounded-full text-xs ${statusConfig.class}`"
        >
            {{ statusConfig.label }}
        </Badge>
        <Badge
            v-if="props.isSystem"
            variant="outline"
            class="rounded-full text-xs"
        >
            {{ __('System') }}
        </Badge>
    </div>
</template>
