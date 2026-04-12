<script setup lang="ts">
import {
    ExternalLink,
    History,
    KeyRound,
    PackageCheck,
    Timer,
} from 'lucide-vue-next';
import { computed } from 'vue';
import ApprovalStatusBadge from '@/components/cumpu/approvals/ApprovalStatusBadge.vue';
import { useTranslations } from '@/lib/translations';
import type { ApprovalContext } from '@/types/cumpu';

const props = defineProps<{
    context: ApprovalContext;
}>();

const { __, locale } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

function formatDate(date: string): string {
    return dateFormatter.value.format(new Date(date));
}

const visible = computed(
    () => props.context.can_view_requests && props.context.history.length > 0,
);
</script>

<template>
    <div
        v-if="visible"
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
    >
        <!-- Header -->
        <div
            class="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3"
        >
            <History class="size-3.5 shrink-0 text-muted-foreground" />
            <h2 class="text-sm font-semibold text-foreground">
                {{ __('Approval history') }}
            </h2>
            <span class="text-xs text-muted-foreground">
                – {{ props.context.scope_label }}
            </span>
        </div>

        <!-- Item list -->
        <ul class="divide-y divide-sidebar-border/40">
            <li
                v-for="item in props.context.history"
                :key="item.id"
                class="flex items-center gap-3 px-4 py-3"
            >
                <ApprovalStatusBadge :status="item.status" size="sm" />

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ item.action_label }}
                    </p>
                    <div
                        class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                    >
                        <span v-if="item.current_step_label">
                            {{ item.current_step_label }}
                        </span>
                        <span v-if="item.requested_by_name">
                            <template v-if="item.current_step_label"
                                >·</template
                            >
                            {{ item.requested_by_name }}
                        </span>
                        <span>
                            <template
                                v-if="
                                    item.current_step_label ||
                                    item.requested_by_name
                                "
                            >
                                ·
                            </template>
                            {{ formatDate(item.requested_at) }}
                        </span>
                    </div>
                    <!-- Grant lifecycle inline indicators -->
                    <div
                        v-if="
                            item.is_grant_usable ||
                            item.status === 'consumed' ||
                            item.is_grant_expired
                        "
                        class="mt-1 flex items-center gap-1.5"
                    >
                        <template v-if="item.is_grant_usable">
                            <KeyRound
                                class="size-3 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <span
                                class="text-xs text-emerald-700 dark:text-emerald-400"
                            >
                                {{ __('Grant ready') }}
                                <template v-if="item.expires_at">
                                    – {{ __('expires') }}
                                    {{ formatDate(item.expires_at) }}
                                </template>
                            </span>
                        </template>
                        <template v-else-if="item.status === 'consumed'">
                            <PackageCheck
                                class="size-3 shrink-0 text-violet-600 dark:text-violet-400"
                            />
                            <span
                                class="text-xs text-violet-700 dark:text-violet-400"
                            >
                                {{ __('Grant used') }}
                                <template v-if="item.consumed_by_name">
                                    – {{ item.consumed_by_name }}
                                </template>
                                <template v-if="item.consumed_at">
                                    <template v-if="item.consumed_by_name">
                                        ·
                                    </template>
                                    {{ formatDate(item.consumed_at) }}
                                </template>
                            </span>
                        </template>
                        <template v-else-if="item.is_grant_expired">
                            <Timer
                                class="size-3 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <span
                                class="text-xs text-amber-700 dark:text-amber-400"
                            >
                                {{ __('Grant expired') }}
                                <template v-if="item.expires_at">
                                    – {{ formatDate(item.expires_at) }}
                                </template>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Link to Cumpu only when detail_url is available -->
                <a
                    v-if="item.detail_url"
                    :href="item.detail_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :aria-label="__('View approval request in Cumpu')"
                    class="shrink-0 text-muted-foreground/50 transition-colors hover:text-foreground"
                >
                    <ExternalLink class="size-3.5" />
                </a>
                <div v-else class="size-3.5 shrink-0" aria-hidden="true" />
            </li>
        </ul>
    </div>
</template>
