<script setup lang="ts">
import { computed } from 'vue';
import { Toaster } from 'vue-sonner';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance } = useAppearance();

const theme = computed(() => resolvedAppearance.value);
</script>

<template>
    <Toaster
        :theme="theme"
        rich-colors
        close-button
        :expand="false"
        :visible-toasts="4"
        position="top-right"
    />
</template>
