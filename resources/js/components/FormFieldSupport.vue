<script setup lang="ts">
defineProps<{
    hint?: string;
    error?: string;
}>();
</script>

<template>
    <!--
        Always occupies at least one text line so that toggling the hint
        (e.g. "Using system default…") never causes a layout shift.
        Error takes priority over hint when both are present.
    -->
    <div
        data-slot="form-field-support"
        :data-state="error ? 'error' : hint ? 'hint' : 'empty'"
        aria-live="polite"
        class="min-h-5"
    >
        <p v-if="error" class="text-sm text-red-600 dark:text-red-500">
            {{ error }}
        </p>
        <p v-else-if="hint" class="text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
