<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    LayoutGrid,
    MonitorSmartphone,
    PanelsTopLeft,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';

const { activeApp, dashboardBreadcrumbs } = useAppNavigation();
const { __ } = useTranslations();

const shellHighlights = computed(() => [
    {
        title: __('Current app'),
        description: activeApp.value.title,
        detail: activeApp.value.subtitle,
        icon: LayoutGrid,
    },
    {
        title: __('Sidebar navigation'),
        description: __('Main menu'),
        detail: __('Use the app switcher to move between Tyanc and Demo.'),
        icon: ArrowRightLeft,
    },
    {
        title: __('Responsive shell'),
        description: __('Dashboard'),
        detail: __(
            'Desktop and mobile navigation stay in sync through the shared shell.',
        ),
        icon: MonitorSmartphone,
    },
]);
</script>

<template>
    <Head :title="__('Dashboard')" />

    <AppLayout :breadcrumbs="dashboardBreadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:gap-5">
            <Card class="border-sidebar-border/70 bg-sidebar/25 shadow-none">
                <CardContent
                    class="grid gap-6 px-5 py-5 md:grid-cols-[minmax(0,1fr)_220px] md:px-6"
                >
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="outline">{{
                                __('Dashboard')
                            }}</Badge>
                            <Badge variant="outline">
                                {{ activeApp.subtitle }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                {{ activeApp.title }}
                            </h1>
                            <p
                                class="max-w-2xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    __(
                                        'This shared dashboard shell is prepared for dedicated Tyanc and Demo content.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex flex-col justify-between rounded-2xl border border-sidebar-border/70 bg-background/75 p-4"
                    >
                        <div
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <PanelsTopLeft
                                class="size-4 text-muted-foreground"
                            />
                            {{ __('Ready for the next module') }}
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            {{
                                __(
                                    'Dedicated dashboard modules land here next.',
                                )
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-3">
                <Card
                    v-for="section in shellHighlights"
                    :key="section.title"
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-xl border border-sidebar-border/70 bg-sidebar/35 text-muted-foreground"
                        >
                            <component :is="section.icon" class="size-4" />
                        </div>
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                {{ section.title }}
                            </CardTitle>
                            <CardDescription class="text-foreground">
                                {{ section.description }}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ section.detail }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
