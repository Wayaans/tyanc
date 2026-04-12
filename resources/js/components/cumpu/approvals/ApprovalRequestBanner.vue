<script setup lang="ts">
import { Clock, ExternalLink } from 'lucide-vue-next';
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
</script>

<template>
    <div
        v-if="props.context.has_pending_requests"
        class="rounded-xl border border-amber-200/60 bg-amber-50/50 px-4 py-3.5 dark:border-amber-500/20 dark:bg-amber-500/[0.07]"
    >
        <div class="flex items-start gap-3">
            <!-- Icon -->
            <div
                class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/15"
            >
                <Clock class="size-3.5 text-amber-600 dark:text-amber-400" />
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1 space-y-1.5">
                <p
                    class="flex flex-wrap items-center gap-1.5 text-sm font-medium text-amber-900 dark:text-amber-200"
                >
                    {{ props.context.scope_label }}
                    <span
                        v-if="props.context.pending_count > 1"
                        class="inline-flex items-center rounded-full bg-amber-200/70 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                    >
                        {{ props.context.pending_count }}
                        {{ __('pending') }}
                    </span>
                    <span
                        v-else
                        class="text-xs font-normal text-amber-700/70 dark:text-amber-400/70"
                    >
                        {{ __('pending approval') }}
                    </span>
                </p>

                <div
                    v-if="props.context.latest_pending_request"
                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                >
                    <ApprovalStatusBadge
                        :status="props.context.latest_pending_request.status"
                        size="sm"
                    />
                    <span
                        class="text-xs text-amber-700/80 dark:text-amber-300/80"
                    >
                        {{ props.context.latest_pending_request.action_label }}
                    </span>
                    <span
                        v-if="
                            props.context.latest_pending_request
                                .current_step_label
                        "
                        class="text-xs text-amber-600/70 dark:text-amber-400/60"
                    >
                        ·
                        {{
                            props.context.latest_pending_request
                                .current_step_label
                        }}
                    </span>
                    <span
                        v-if="
                            props.context.latest_pending_request
                                .requested_by_name
                        "
                        class="text-xs text-amber-600/70 dark:text-amber-400/60"
                    >
                        ·
                        {{
                            props.context.latest_pending_request
                                .requested_by_name
                        }}
                    </span>
                    <span
                        class="text-xs text-amber-600/60 dark:text-amber-400/50"
                    >
                        ·
                        {{
                            formatDate(
                                props.context.latest_pending_request
                                    .requested_at,
                            )
                        }}
                    </span>
                </div>
            </div>

            <!-- CTA: only when detail_url is available -->
            <a
                v-if="props.context.latest_pending_request?.detail_url"
                :href="props.context.latest_pending_request.detail_url"
                target="_blank"
                rel="noopener noreferrer"
                class="flex shrink-0 items-center gap-1 rounded-lg border border-amber-200/80 bg-white/60 px-2.5 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100/60 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
            >
                {{ __('View in Cumpu') }}
                <ExternalLink class="size-3" />
            </a>
        </div>
    </div>
</template>
