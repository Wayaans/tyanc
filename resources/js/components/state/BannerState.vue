<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

type Variant = 'default' | 'warning';

const props = withDefaults(
    defineProps<{
        title?: string;
        description: string;
        icon?: Component | null;
        variant?: Variant;
    }>(),
    {
        title: undefined,
        icon: null,
        variant: 'default',
    },
);

const containerClass = computed(() =>
    props.variant === 'warning'
        ? 'border-amber-500/30 bg-amber-500/10'
        : 'border-border/70 bg-muted/30',
);

const iconClass = computed(() =>
    props.variant === 'warning'
        ? 'text-amber-700 dark:text-amber-300'
        : 'text-muted-foreground',
);
</script>

<template>
    <div
        :class="['rounded-xl border px-4 py-3 text-sm', containerClass]"
        role="alert"
    >
        <div class="flex items-start gap-3">
            <component
                :is="icon"
                v-if="icon"
                :class="['mt-0.5 size-4 shrink-0', iconClass]"
            />

            <div class="min-w-0 flex-1 space-y-1">
                <p v-if="title" class="font-medium text-foreground">
                    {{ title }}
                </p>
                <p class="leading-6 text-muted-foreground">
                    {{ description }}
                </p>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center gap-3 pt-1"
                >
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </div>
</template>
