<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

type Variant = 'default' | 'warning';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        icon?: Component | null;
        variant?: Variant;
    }>(),
    {
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
        ? 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300'
        : 'border-border/70 bg-background text-muted-foreground',
);
</script>

<template>
    <div :class="['rounded-2xl border px-5 py-4', containerClass]" role="alert">
        <div class="flex items-start gap-3">
            <div
                v-if="icon"
                :class="[
                    'flex size-10 shrink-0 items-center justify-center rounded-full border',
                    iconClass,
                ]"
            >
                <component :is="icon" class="size-4" />
            </div>

            <div class="min-w-0 flex-1 space-y-1.5">
                <h2 class="text-sm font-semibold text-foreground">
                    {{ title }}
                </h2>
                <p class="text-sm leading-6 text-muted-foreground">
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
