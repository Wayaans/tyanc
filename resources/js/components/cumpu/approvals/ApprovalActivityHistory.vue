<script setup lang="ts">
import {
    AlertTriangle,
    ArrowRightLeft,
    Ban,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    CircleDot,
    History,
    PackageCheck,
    PlusCircle,
    Timer,
    UserCheck,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useTranslations } from '@/lib/translations';
import type { ActivityRow, ApprovalRequestRow } from '@/types';

const props = defineProps<{
    history: ActivityRow[];
    approval?: ApprovalRequestRow | null;
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

type TimelineDetail = {
    label: string;
    value: string;
    toneClass?: string;
};

type EventConfig = {
    title: string;
    summary: string | null;
    icon: typeof CircleDot;
    dotClass: string;
    lineClass: string;
};

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

function formatDate(date: string): string {
    return dateFormatter.value.format(new Date(date));
}

function normalizeEvent(event: string | null | undefined): string {
    const value = (event ?? '').toLowerCase();

    if (value.includes('consum')) {
        return 'consumed';
    }

    if (value.includes('expir')) {
        return 'expired';
    }

    if (value.includes('escalat')) {
        return 'escalated';
    }

    if (value.includes('reassign')) {
        return 'reassigned';
    }

    if (value.includes('reminder')) {
        return 'reminder';
    }

    if (value.includes('reject')) {
        return 'rejected';
    }

    if (value.includes('cancel')) {
        return 'cancelled';
    }

    if (value.includes('advanc')) {
        return 'advanced';
    }

    if (
        value.includes('create') ||
        value.includes('submit') ||
        value.includes('request')
    ) {
        return 'requested';
    }

    if (value.includes('approv')) {
        return 'approved';
    }

    if (value.includes('assign')) {
        return 'assigned';
    }

    return value === '' ? 'activity' : value;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function rowProperties(row: ActivityRow): Record<string, unknown> {
    return isRecord(row.properties) ? row.properties : {};
}

function approvalAttributes(row: ActivityRow): Record<string, unknown> {
    const properties = rowProperties(row);

    return isRecord(properties.attributes) ? properties.attributes : {};
}

function stringValue(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}

function scalarText(value: unknown): string | null {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : null;
}

function approvalDate(value: string | null | undefined): string | null {
    return typeof value === 'string' && value !== '' ? formatDate(value) : null;
}

function eventConfig(row: ActivityRow): EventConfig {
    switch (normalizeEvent(row.event)) {
        case 'requested':
            return {
                title: __('Request submitted'),
                summary: __(
                    'Approval was requested before the governed action could be retried.',
                ),
                icon: PlusCircle,
                dotClass:
                    'bg-slate-500/15 text-slate-600 ring-slate-500/30 dark:text-slate-400',
                lineClass: 'bg-border',
            };
        case 'advanced':
            return {
                title: __('Moved to the next workflow step'),
                summary: __(
                    'The current reviewer completed their step and the next reviewers were notified.',
                ),
                icon: UserCheck,
                dotClass:
                    'bg-sky-500/15 text-sky-600 ring-sky-500/30 dark:text-sky-400',
                lineClass: 'bg-sky-200 dark:bg-sky-800/60',
            };
        case 'approved':
            return {
                title: __('Grant issued'),
                summary: __(
                    'Review finished and a one-time approval grant is now ready for the requester to use.',
                ),
                icon: CheckCircle2,
                dotClass:
                    'bg-emerald-500/15 text-emerald-600 ring-emerald-500/30 dark:text-emerald-400',
                lineClass: 'bg-emerald-200 dark:bg-emerald-800/60',
            };
        case 'rejected':
            return {
                title: __('Request rejected'),
                summary: __(
                    'The request was declined and the governed action remains blocked.',
                ),
                icon: XCircle,
                dotClass:
                    'bg-red-500/15 text-red-600 ring-red-500/30 dark:text-red-400',
                lineClass: 'bg-red-200 dark:bg-red-800/60',
            };
        case 'cancelled':
            return {
                title: __('Request cancelled'),
                summary: __(
                    'The requester cancelled the approval request before a decision was applied.',
                ),
                icon: Ban,
                dotClass:
                    'bg-orange-500/15 text-orange-600 ring-orange-500/30 dark:text-orange-400',
                lineClass: 'bg-orange-200 dark:bg-orange-800/60',
            };
        case 'reassigned':
            return {
                title: __('Step reassigned'),
                summary: __(
                    'The active workflow step was moved to a different reviewer.',
                ),
                icon: ArrowRightLeft,
                dotClass:
                    'bg-violet-500/15 text-violet-600 ring-violet-500/30 dark:text-violet-400',
                lineClass: 'bg-violet-200 dark:bg-violet-800/60',
            };
        case 'reminder':
            return {
                title: __('Reminder sent'),
                summary: __(
                    'Current reviewers were reminded that the request is still waiting for a decision.',
                ),
                icon: AlertTriangle,
                dotClass:
                    'bg-amber-500/15 text-amber-600 ring-amber-500/30 dark:text-amber-400',
                lineClass: 'bg-amber-200 dark:bg-amber-800/60',
            };
        case 'escalated':
            return {
                title: __('Request escalated'),
                summary: __(
                    'The request stayed pending long enough to trigger escalation.',
                ),
                icon: AlertTriangle,
                dotClass:
                    'bg-amber-500/15 text-amber-600 ring-amber-500/30 dark:text-amber-400',
                lineClass: 'bg-amber-200 dark:bg-amber-800/60',
            };
        case 'consumed':
            return {
                title: __('Grant consumed'),
                summary: __(
                    'The requester retried the governed action and used the one-time grant.',
                ),
                icon: PackageCheck,
                dotClass:
                    'bg-violet-500/15 text-violet-600 ring-violet-500/30 dark:text-violet-400',
                lineClass: 'bg-violet-200 dark:bg-violet-800/60',
            };
        case 'expired':
            return {
                title: __('Grant expired'),
                summary: __(
                    'The approved grant was not used before it expired.',
                ),
                icon: Timer,
                dotClass:
                    'bg-amber-500/15 text-amber-600 ring-amber-500/30 dark:text-amber-400',
                lineClass: 'bg-amber-200 dark:bg-amber-800/60',
            };
        default:
            return {
                title: row.description,
                summary: null,
                icon: CircleDot,
                dotClass: 'bg-sidebar text-muted-foreground ring-border',
                lineClass: 'bg-border',
            };
    }
}

function detail(
    label: string,
    value: string | null,
    toneClass?: string,
): TimelineDetail | null {
    if (value === null) {
        return null;
    }

    return { label, value, toneClass };
}

function detailItems(row: ActivityRow): TimelineDetail[] {
    const approval = props.approval;
    const properties = rowProperties(row);
    const attributes = approvalAttributes(row);
    const event = normalizeEvent(row.event);

    switch (event) {
        case 'requested':
            return [
                detail(__('Action'), approval?.action_label ?? row.description),
                detail(
                    __('Reason'),
                    stringValue(attributes.request_note) ??
                        approval?.request_note ??
                        null,
                    'text-foreground',
                ),
                detail(__('Subject'), approval?.subject_name ?? null),
                detail(
                    __('Submitted'),
                    approvalDate(approval?.requested_at ?? row.created_at),
                ),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'advanced':
            return [
                detail(
                    __('Completed step'),
                    stringValue(properties.completed_step_label) ??
                        scalarText(properties.completed_step_order) ??
                        null,
                ),
                detail(
                    __('Next step'),
                    stringValue(properties.next_step_label) ??
                        scalarText(properties.next_step_order) ??
                        null,
                ),
                detail(__('Review note'), stringValue(properties.review_note)),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'approved':
            return [
                detail(
                    __('Reviewed by'),
                    approval?.reviewed_by_name ?? row.causer_name,
                ),
                detail(
                    __('Grant valid until'),
                    approvalDate(
                        stringValue(properties.expires_at) ??
                            approval?.expires_at ??
                            null,
                    ),
                    'text-emerald-700 dark:text-emerald-400',
                ),
                detail(
                    __('Review note'),
                    stringValue(properties.review_note) ??
                        approval?.review_note ??
                        null,
                ),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'rejected':
            return [
                detail(
                    __('Reviewed by'),
                    approval?.reviewed_by_name ?? row.causer_name,
                ),
                detail(
                    __('Review note'),
                    stringValue(properties.review_note) ??
                        approval?.review_note ??
                        null,
                ),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'cancelled':
            return [
                detail(__('Cancelled by'), row.causer_name),
                detail(__('Cancelled at'), approvalDate(row.created_at)),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'reassigned':
            return [
                detail(
                    __('Step'),
                    stringValue(properties.step_label) ??
                        scalarText(properties.step_order) ??
                        approval?.current_step_label ??
                        null,
                ),
                detail(
                    __('Reassigned to'),
                    stringValue(properties.reassigned_to_name),
                ),
                detail(__('Note'), stringValue(properties.note)),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'reminder':
        case 'escalated':
            return [
                detail(
                    __('Current step'),
                    approval?.current_step_label ?? null,
                ),
                detail(
                    __('Submitted'),
                    approvalDate(approval?.requested_at ?? null),
                ),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'consumed':
            return [
                detail(
                    __('Used by'),
                    approval?.consumed_by_name ?? row.causer_name,
                    'text-violet-700 dark:text-violet-400',
                ),
                detail(
                    __('Used at'),
                    approvalDate(approval?.consumed_at ?? row.created_at),
                ),
            ].filter((item): item is TimelineDetail => item !== null);
        case 'expired':
            return [
                detail(
                    __('Expired at'),
                    approvalDate(
                        stringValue(properties.expires_at) ??
                            approval?.expires_at ??
                            row.created_at,
                    ),
                    'text-amber-700 dark:text-amber-400',
                ),
                detail(__('Next step'), __('Submit a new approval request.')),
            ].filter((item): item is TimelineDetail => item !== null);
        default:
            return [];
    }
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
                {{ __('Activity timeline') }}
            </h2>
            <span
                v-if="props.history.length > 0"
                class="ml-auto text-xs text-muted-foreground tabular-nums"
            >
                {{ props.history.length }}
                {{ props.history.length === 1 ? __('event') : __('events') }}
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
                <div
                    v-if="index < props.history.length - 1"
                    :class="[
                        'absolute top-8 bottom-0 left-[13px] z-0 w-0.5',
                        eventConfig(row).lineClass,
                    ]"
                />

                <div
                    :class="[
                        'relative z-10 mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full ring-1',
                        eventConfig(row).dotClass,
                    ]"
                >
                    <component :is="eventConfig(row).icon" class="size-3.5" />
                </div>

                <div class="min-w-0 flex-1 space-y-2 pb-4">
                    <div class="space-y-1">
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <p class="text-sm font-medium text-foreground">
                                {{ eventConfig(row).title }}
                            </p>
                            <span
                                v-if="row.causer_name"
                                class="text-xs text-muted-foreground"
                            >
                                {{ __('by') }} {{ row.causer_name }}
                            </span>
                        </div>
                        <p
                            v-if="eventConfig(row).summary"
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ eventConfig(row).summary }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                    >
                        <time :datetime="row.created_at">
                            {{ formatDate(row.created_at) }}
                        </time>

                        <button
                            v-if="hasProperties(row)"
                            type="button"
                            class="ml-0.5 flex items-center gap-0.5 text-xs text-muted-foreground/70 transition-colors hover:text-foreground"
                            @click="toggleExpand(row.id)"
                        >
                            <component
                                :is="
                                    expandedIds.has(row.id)
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="size-3"
                            />
                            {{
                                expandedIds.has(row.id)
                                    ? __('Hide raw details')
                                    : __('Show raw details')
                            }}
                        </button>
                    </div>

                    <div
                        v-if="detailItems(row).length > 0"
                        class="grid gap-2 sm:grid-cols-2"
                    >
                        <div
                            v-for="item in detailItems(row)"
                            :key="`${row.id}-${item.label}`"
                            class="rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2"
                        >
                            <p
                                class="text-[11px] tracking-wide text-muted-foreground uppercase"
                            >
                                {{ item.label }}
                            </p>
                            <p
                                :class="[
                                    'mt-1 text-sm leading-snug text-foreground',
                                    item.toneClass,
                                ]"
                            >
                                {{ item.value }}
                            </p>
                        </div>
                    </div>

                    <pre
                        v-if="expandedIds.has(row.id) && hasProperties(row)"
                        class="overflow-x-auto rounded-lg border border-sidebar-border/70 bg-sidebar/10 p-2.5 text-xs text-muted-foreground"
                        >{{ formatProperties(row) }}</pre
                    >
                </div>
            </li>
        </ul>
    </div>
</template>
