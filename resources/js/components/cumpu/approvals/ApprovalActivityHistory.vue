<script setup lang="ts">
import { ChevronDown, ChevronUp, History } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ActivityEventBadge from '@/components/tyanc/activity/ActivityEventBadge.vue';
import { useTranslations } from '@/lib/translations';
import type { ActivityRow } from '@/types';

const props = defineProps<{
    history: ActivityRow[];
}>();

const { __ } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const expandedIds = ref<Set<string>>(new Set());

function toggleExpand(id: string) {
    const nextExpandedIds = new Set(expandedIds.value);

    if (nextExpandedIds.has(id)) {
        nextExpandedIds.delete(id);
    } else {
        nextExpandedIds.add(id);
    }

    expandedIds.value = nextExpandedIds;
}

function hasProperties(row: ActivityRow): boolean {
    return (
        row.properties !== null &&
        row.properties !== undefined &&
        Object.keys(row.properties).length > 0
    );
}

function formatProperties(row: ActivityRow): string {
    return JSON.stringify(row.properties, null, 2);
}
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
    >
        <div
            class="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3"
        >
            <History class="size-3.5 shrink-0 text-muted-foreground" />
            <h2 class="text-sm font-semibold text-foreground">
                {{ __('Activity history') }}
            </h2>
            <span
                v-if="props.history.length > 0"
                class="ml-auto text-xs text-muted-foreground tabular-nums"
            >
                {{ props.history.length }}
            </span>
        </div>

        <div
            v-if="props.history.length === 0"
            class="flex flex-col items-center gap-2 py-10 text-center"
        >
            <History class="size-7 text-muted-foreground/30" />
            <p class="text-sm text-muted-foreground">
                {{ __('No activity recorded yet.') }}
            </p>
        </div>

        <ul v-else class="space-y-0 p-3">
            <li
                v-for="(row, index) in props.history"
                :key="row.id"
                class="relative flex items-start gap-3"
            >
                <!-- Connector line -->
                <div
                    v-if="index < props.history.length - 1"
                    class="absolute top-6 bottom-0 left-[11px] z-0 w-0.5 bg-border"
                />

                <!-- Dot -->
                <div
                    class="relative z-10 mt-1.5 size-5 shrink-0 rounded-full border-2 border-background bg-sidebar ring-1 ring-border"
                />

                <!-- Content -->
                <div class="min-w-0 flex-1 space-y-1 pb-4">
                    <div class="flex flex-wrap items-start gap-x-2 gap-y-1">
                        <ActivityEventBadge :event="row.event" />
                        <p class="min-w-0 text-sm leading-snug text-foreground">
                            {{ row.description }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-0 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="row.causer_name"
                            class="font-medium text-foreground/80"
                        >
                            {{ row.causer_name }}
                        </span>
                        <span
                            v-if="row.causer_name"
                            class="select-none"
                            aria-hidden="true"
                            >·</span
                        >
                        <span>
                            {{ dateFormatter.format(new Date(row.created_at)) }}
                        </span>

                        <button
                            v-if="hasProperties(row)"
                            type="button"
                            class="ml-1 flex items-center gap-0.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                            @click="toggleExpand(row.id)"
                        >
                            {{ __('Details') }}
                            <component
                                :is="
                                    expandedIds.has(row.id)
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="size-3"
                            />
                        </button>
                    </div>

                    <pre
                        v-if="expandedIds.has(row.id) && hasProperties(row)"
                        class="mt-1 overflow-x-auto rounded-lg border border-sidebar-border/70 bg-sidebar/10 p-2.5 text-xs text-muted-foreground"
                        >{{ formatProperties(row) }}</pre
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
