<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useNotificationStore } from '@/composables/useNotificationStore';
import { notify } from '@/lib/notify';
import type { FlashProps, ToastPayload } from '@/types';

const page = usePage();
const { hasDisplayedFlashToast, rememberFlashToast } = useNotificationStore();

const flashToast = computed<ToastPayload | null>(() => {
    const flash = (page.props.flash as FlashProps | undefined) ?? null;

    return flash?.toast ?? null;
});

let stopWatchingFlashToast: (() => void) | null = null;

onMounted(() => {
    stopWatchingFlashToast = watch(
        flashToast,
        (toastPayload) => {
            if (!toastPayload || hasDisplayedFlashToast(toastPayload)) {
                return;
            }

            rememberFlashToast(toastPayload);
            notify.show(toastPayload);
        },
        { immediate: true },
    );
});

onUnmounted(() => {
    stopWatchingFlashToast?.();
});
</script>

<template />
