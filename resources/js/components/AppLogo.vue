<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useBranding } from '@/composables/useBranding';

const { appName, appLogo } = useBranding();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-foreground text-background"
    >
        <img
            v-if="appLogo"
            :src="appLogo"
            :alt="appName"
            class="size-5 rounded-sm object-contain"
        />
        <AppLogoIcon v-else class="size-5 fill-current" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            {{ appName }}
        </span>
    </div>
</template>
