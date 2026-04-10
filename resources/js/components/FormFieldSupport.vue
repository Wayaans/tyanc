<script setup lang="ts">
defineProps<{
    hint?: string;
    error?: string;
}>();
</script>

<template>
    <!-- Error takes priority over hint when both are present. -->
    <div
        v-if="error || hint"
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
    </div>
</template>
