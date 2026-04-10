<script setup lang="ts">
import { CheckCircle2, Clock, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { ApprovalRequestRow, ApprovalStatus } from '@/types';

const props = defineProps<{
    approvalRequests: ApprovalRequestRow[];
}>();

const emit = defineEmits<{
    decide: [request: ApprovalRequestRow];
}>();

const { __ } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const statusConfigs: Record<
    ApprovalStatus,
    { label: string; badgeClass: string; icon: typeof Clock; iconClass: string }
> = {
    pending: {
        label: 'Pending approval',
        badgeClass:
            'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
        icon: Clock,
        iconClass: 'text-slate-500',
    },
    approved: {
        label: 'Approval approved',
        badgeClass:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        icon: CheckCircle2,
        iconClass: 'text-emerald-500',
    },
    rejected: {
        label: 'Approval rejected',
        badgeClass:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
        icon: XCircle,
        iconClass: 'text-red-500',
    },
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
    >
        <div
            class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3"
        >
            <h2 class="text-sm font-semibold text-foreground">
                {{ __('Approvals') }}
            </h2>
            <Badge
                v-if="
                    props.approvalRequests.filter(
                        (request) => request.status === 'pending',
                    ).length > 0
                "
                variant="outline"
                class="rounded-full text-xs"
            >
                {{
                    props.approvalRequests.filter(
                        (request) => request.status === 'pending',
                    ).length
                }}
                {{ __('Pending approval') }}
            </Badge>
        </div>

        <div
            v-if="props.approvalRequests.length === 0"
            class="flex flex-col items-center gap-2 py-10 text-center"
        >
            <CheckCircle2 class="size-8 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">
                {{ __('No approval requests yet.') }}
            </p>
        </div>

        <ul v-else class="divide-y divide-sidebar-border/50">
            <li
                v-for="request in props.approvalRequests"
                :key="request.id"
                class="flex items-start gap-4 px-4 py-4"
            >
                <div
                    :class="[
                        'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full border-2 border-background bg-background shadow-sm',
                        statusConfigs[request.status].iconClass,
                    ]"
                >
                    <component
                        :is="statusConfigs[request.status].icon"
                        class="size-4"
                    />
                </div>

                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium text-foreground">
                            {{ request.subject_name }}
                        </p>
                        <Badge
                            variant="outline"
                            :class="`rounded-full text-xs ${statusConfigs[request.status].badgeClass}`"
                        >
                            {{ __(statusConfigs[request.status].label) }}
                        </Badge>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        {{ request.action_label }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        {{ __('Requested by') }}
                        <span class="font-medium text-foreground">
                            {{ request.requested_by_name ?? __('Unknown') }}
                        </span>
                        ·
                        {{
                            dateFormatter.format(new Date(request.requested_at))
                        }}
                    </p>

                    <p
                        v-if="request.reviewed_at && request.reviewed_by_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ __('Reviewed by') }}
                        <span class="font-medium text-foreground">{{
                            request.reviewed_by_name
                        }}</span>
                        ·
                        {{
                            dateFormatter.format(new Date(request.reviewed_at))
                        }}
                    </p>

                    <p
                        v-if="request.request_note"
                        class="text-xs text-muted-foreground"
                    >
                        {{ request.request_note }}
                    </p>

                    <p
                        v-if="request.review_note"
                        class="text-xs text-muted-foreground"
                    >
                        {{ request.review_note }}
                    </p>
                </div>

                <button
                    v-if="
                        request.status === 'pending' &&
                        (request.can_approve || request.can_reject)
                    "
                    class="shrink-0 rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-sidebar/40"
                    @click="emit('decide', request)"
                >
                    {{ __('Approve request') }}
                </button>
            </li>
        </ul>
    </div>
</template>
