<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div data-slot="input-error" aria-live="polite">
        <p v-if="message" class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
