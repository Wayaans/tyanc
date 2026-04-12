<script setup lang="ts">
import {
    AlertTriangle,
    ArrowRightLeft,
    Clock,
    KeyRound,
    PackageCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import ApprovalStatusBadge from '@/components/cumpu/approvals/ApprovalStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import { show } from '@/routes/cumpu/approvals';
import type { DataTableMeta, DataTableQuery } from '@/types';
import type { ApprovalReportRow } from '@/types';

const props = defineProps<{
    rows: ApprovalReportRow[];
    meta: DataTableMeta;
    query: DataTableQuery;
}>();

const emit = defineEmits<{
    pageChange: [page: number];
}>();

const { __ } = useTranslations();

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const hasRows = computed(() => props.rows.length > 0);
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background/80 shadow-none"
    >
        <!-- Table header -->
        <div
            class="grid grid-cols-[minmax(0,2.5fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_auto] gap-3 border-b border-sidebar-border/50 px-4 py-2.5"
        >
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Request') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Status') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Current step') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Requested') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Flags') }}
            </p>
        </div>

        <!-- Empty -->
        <div v-if="!hasRows" class="px-4 py-12 text-center">
            <p class="text-sm font-medium text-foreground">
                {{ __('No approval records match your filters.') }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ __('Try adjusting the date range or other filters.') }}
            </p>
        </div>

        <!-- Rows -->
        <div v-else class="divide-y divide-sidebar-border/40">
            <div
                v-for="row in props.rows"
                :key="row.id"
                class="grid grid-cols-[minmax(0,2.5fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 transition-colors hover:bg-sidebar/10"
            >
                <!-- Request identity -->
                <div class="min-w-0 space-y-0.5">
                    <a
                        :href="show.url({ approvalRequest: row.id })"
                        class="truncate text-sm font-medium text-foreground hover:underline"
                    >
                        {{ row.subject_name }}
                    </a>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge variant="outline" class="rounded-full text-xs">
                            {{ row.app_label ?? row.action_label }}
                        </Badge>
                        <span
                            v-if="row.requested_by_name"
                            class="text-xs text-muted-foreground"
                        >
                            {{ row.requested_by_name }}
                        </span>
                    </div>
                    <!-- Reviewed by -->
                    <p
                        v-if="row.reviewed_by_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ __('Reviewed by') }}
                        <span class="font-medium text-foreground">{{
                            row.reviewed_by_name
                        }}</span>
                    </p>
                    <!-- Consumed by (grant used) -->
                    <p
                        v-if="row.consumed_by_name"
                        class="text-xs text-violet-700 dark:text-violet-400"
                    >
                        {{ __('Used by') }}
                        <span class="font-medium">{{
                            row.consumed_by_name
                        }}</span>
                        <span v-if="row.consumed_at">
                            ·
                            {{
                                dateFormatter.format(new Date(row.consumed_at))
                            }}</span
                        >
                    </p>
                    <!-- Grant expiry (when approved and usable) -->
                    <p
                        v-if="row.is_grant_usable && row.expires_at"
                        class="flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400"
                    >
                        <KeyRound class="size-3" />
                        {{ __('Expires') }}:
                        {{ dateFormatter.format(new Date(row.expires_at)) }}
                    </p>
                    <!-- Grant expired -->
                    <p
                        v-else-if="
                            row.expires_at &&
                            !row.is_grant_usable &&
                            row.consumed_at === null
                        "
                        class="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400"
                    >
                        <KeyRound class="size-3" />
                        {{ __('Expired') }}:
                        {{ dateFormatter.format(new Date(row.expires_at)) }}
                    </p>
                </div>

                <!-- Status -->
                <div>
                    <ApprovalStatusBadge :status="row.status" />
                </div>

                <!-- Current step -->
                <div class="min-w-0 space-y-0.5">
                    <p
                        v-if="row.current_step_label"
                        class="truncate text-sm text-foreground"
                    >
                        {{ row.current_step_label }}
                    </p>
                    <p
                        v-else-if="row.current_step_order !== null"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            __('Step :n', { n: String(row.current_step_order) })
                        }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">—</p>
                    <p
                        v-if="row.current_assignee_names?.length"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ row.current_assignee_names.join(', ') }}
                    </p>
                </div>

                <!-- Requested at -->
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ dateFormatter.format(new Date(row.requested_at)) }}
                </p>

                <!-- Flags -->
                <div class="flex items-center gap-1">
                    <span
                        v-if="row.is_overdue"
                        :title="__('Overdue')"
                        class="flex size-5 items-center justify-center rounded-full bg-amber-500/10 text-amber-600"
                    >
                        <Clock class="size-3" />
                    </span>
                    <span
                        v-if="row.is_escalated"
                        :title="__('Escalated')"
                        class="flex size-5 items-center justify-center rounded-full bg-amber-500/10 text-amber-600"
                    >
                        <AlertTriangle class="size-3" />
                    </span>
                    <span
                        v-if="row.is_reassigned"
                        :title="__('Reassigned')"
                        class="flex size-5 items-center justify-center rounded-full bg-violet-500/10 text-violet-600"
                    >
                        <ArrowRightLeft class="size-3" />
                    </span>
                </div>
            </div>
        </div>

        <!-- Pagination footer -->
        <div
            v-if="props.meta.has_pages"
            class="flex items-center justify-between border-t border-sidebar-border/50 px-4 py-3"
        >
            <p class="text-xs text-muted-foreground">
                {{
                    __(':from–:to of :total', {
                        from: String(props.meta.from ?? 0),
                        to: String(props.meta.to ?? 0),
                        total: String(props.meta.total),
                    })
                }}
            </p>
            <div class="flex items-center gap-1">
                <button
                    :disabled="props.query.page <= 1"
                    class="rounded px-2 py-1 text-xs text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                    @click="emit('pageChange', props.query.page - 1)"
                >
                    ←
                </button>
                <span class="text-xs text-muted-foreground tabular-nums">
                    {{ props.query.page }} / {{ props.meta.last_page }}
                </span>
                <button
                    :disabled="props.query.page >= props.meta.last_page"
                    class="rounded px-2 py-1 text-xs text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                    @click="emit('pageChange', props.query.page + 1)"
                >
                    →
                </button>
            </div>
        </div>
    </div>
</template>
