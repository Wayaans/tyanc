<script setup lang="ts">
defineProps<{
    hint?: string;
    error?: string;
}>();
</script>

<template>
    <div
        v-if="error || hint || $slots.default"
        data-slot="form-field-support"
        :data-state="error ? 'error' : 'hint'"
        aria-live="polite"
    >
        <p
            v-if="error"
            class="text-sm leading-4 text-red-600 dark:text-red-500"
        >
            {{ error }}
        </p>
        <p v-else-if="hint" class="text-xs leading-4 text-muted-foreground">
            {{ hint }}
        </p>
        <div v-else class="text-xs leading-4 text-muted-foreground">
            <slot />
        </div>
    </div>
</template>
