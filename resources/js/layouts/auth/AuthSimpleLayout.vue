<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useBranding } from '@/composables/useBranding';
import { home } from '@/routes';

const { appName, appLogo } = useBranding();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <div class="flex w-full items-center justify-between">
                        <Link
                            :href="home()"
                            class="flex flex-col items-center gap-2 font-medium"
                        >
                            <div
                                class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                            >
                                <img
                                    v-if="appLogo"
                                    :src="appLogo"
                                    :alt="appName"
                                    class="size-9 rounded-sm object-contain"
                                />
                                <AppLogoIcon
                                    v-else
                                    class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                                />
                            </div>
                            <span class="sr-only">{{ title }}</span>
                        </Link>
                        <LanguageSwitcher />
                    </div>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
