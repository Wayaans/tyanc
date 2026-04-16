<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
                <div class="flex justify-center">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-md"
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
                        <span class="sr-only">{{ appName }}</span>
                    </Link>
                </div>

                <Card class="border-border/80 bg-background/95 shadow-lg">
                    <CardHeader class="px-6 pb-0 text-center">
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <CardDescription>{{ description }}</CardDescription>
                    </CardHeader>
                    <CardContent class="px-6">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
