<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/lib/translations';

const props = defineProps<{
    names: string[];
}>();

const { __ } = useTranslations();

const label = computed(() => {
    if (props.names.length === 0) {
        return '';
    }

    if (props.names.length === 1) {
        return __(':name is typing…', { name: props.names[0] ?? '' });
    }

    return __(':count people are typing…', {
        count: String(props.names.length),
    });
});
</script>

<template>
    <div
        v-if="props.names.length > 0"
        class="flex items-center gap-1.5 px-4 py-1.5 text-xs text-muted-foreground"
        aria-live="polite"
        aria-atomic="true"
    >
        <span class="flex items-end gap-0.5">
            <span
                class="size-1 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.32s]"
            />
            <span
                class="size-1 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.16s]"
            />
            <span
                class="size-1 animate-bounce rounded-full bg-muted-foreground"
            />
        </span>
        <span>{{ label }}</span>
    </div>
</template>
