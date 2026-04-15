<script setup lang="ts">
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import { cn } from '@/lib/utils';
import type {
    TyancDashboardModuleMetric,
    TyancDashboardModuleStatus,
} from '@/types';

const props = defineProps<{
    title: string;
    value: number;
    status: TyancDashboardModuleStatus;
    description: string;
    metrics: TyancDashboardModuleMetric[];
    icon: Component;
    href?: LinkComponentBaseProps['href'];
    class?: HTMLAttributes['class'];
}>();

const { __ } = useTranslations();

const wrapper = computed(() => (props.href ? Link : 'div'));

const statusClass = computed(() => {
    if (props.status === 'Healthy') {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (props.status === 'Attention') {
        return 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300';
    }

    return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
});
</script>

<template>
    <component
        :is="wrapper"
        v-bind="props.href ? { href: props.href } : {}"
        class="block"
    >
        <Card
            :class="
                cn(
                    'border-sidebar-border/70 bg-background/80 shadow-none transition duration-200',
                    props.href
                        ? 'cursor-pointer hover:-translate-y-0.5 hover:border-primary/30 hover:bg-background'
                        : '',
                    props.class,
                )
            "
        >
            <CardHeader class="space-y-4 pb-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <CardTitle
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ title }}
                        </CardTitle>
                        <p
                            class="text-4xl font-semibold tracking-tight text-foreground tabular-nums"
                        >
                            {{ value }}
                        </p>
                    </div>

                    <div
                        class="flex size-10 items-center justify-center rounded-2xl border border-sidebar-border/70 bg-sidebar/30 text-muted-foreground"
                    >
                        <component :is="icon" class="size-4" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        variant="outline"
                        class="rounded-full"
                        :class="statusClass"
                    >
                        {{ __(status) }}
                    </Badge>
                    <span class="text-xs text-muted-foreground">
                        {{ __(description) }}
                    </span>
                </div>
            </CardHeader>

            <CardContent class="space-y-2">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="metric in metrics"
                        :key="metric.label"
                        class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                    >
                        <p
                            class="text-[11px] tracking-widest text-muted-foreground uppercase"
                        >
                            {{ __(metric.label) }}
                        </p>
                        <p
                            class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                        >
                            {{ metric.value }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </component>
</template>
