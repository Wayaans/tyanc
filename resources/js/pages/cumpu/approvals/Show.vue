<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { ref } from 'vue';
import ApprovalActivityHistory from '@/components/cumpu/approvals/ApprovalActivityHistory.vue';
import ApprovalAssignmentsCard from '@/components/cumpu/approvals/ApprovalAssignmentsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { approve, cancel, reject } from '@/routes/cumpu/approvals';
import type { ActivityRow, ApprovalRequestRow, ApprovalStatus } from '@/types';
import type { ApprovalAssignmentRow } from '@/types/cumpu';

const props = defineProps<{
    approval: ApprovalRequestRow;
    assignments: ApprovalAssignmentRow[];
    history: ActivityRow[];
    backLink: {
        label: string;
        href: string;
    };
}>();

const { __ } = useTranslations();
const { cumpuApprovalShowBreadcrumbs } = useAppNavigation();

const breadcrumbs = cumpuApprovalShowBreadcrumbs(
    props.backLink.label,
    props.backLink.href,
    props.approval.subject_name,
    props.approval.id,
);

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

type StatusConfig = {
    label: string;
    badgeClass: string;
};

const statusConfigs: Record<ApprovalStatus, StatusConfig> = {
    draft: {
        label: 'Draft',
        badgeClass:
            'border-zinc-500/20 bg-zinc-500/10 text-zinc-700 dark:text-zinc-300',
    },
    pending: {
        label: 'Pending',
        badgeClass:
            'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    },
    in_review: {
        label: 'In review',
        badgeClass:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
    approved: {
        label: 'Approved',
        badgeClass:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    rejected: {
        label: 'Rejected',
        badgeClass:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    },
    cancelled: {
        label: 'Cancelled',
        badgeClass:
            'border-orange-500/20 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    },
    expired: {
        label: 'Expired',
        badgeClass:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    superseded: {
        label: 'Superseded',
        badgeClass:
            'border-stone-500/20 bg-stone-500/10 text-stone-700 dark:text-stone-300',
    },
};

function statusConfig(status: ApprovalStatus): StatusConfig {
    return statusConfigs[status] ?? statusConfigs.pending;
}

function hasContent(obj: Record<string, unknown> | null | undefined): boolean {
    return obj !== null && obj !== undefined && Object.keys(obj).length > 0;
}

function formatJson(obj: Record<string, unknown>): string {
    return JSON.stringify(obj, null, 2);
}

const reviewNote = ref('');
const isSubmitting = ref(false);
const snapshotOpen = ref(false);
const beforePayloadOpen = ref(false);
const afterPayloadOpen = ref(false);

function submitApprove() {
    isSubmitting.value = true;

    router.patch(
        approve.url({ approvalRequest: props.approval.id }),
        { review_note: reviewNote.value || undefined },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function submitReject() {
    isSubmitting.value = true;

    router.patch(
        reject.url({ approvalRequest: props.approval.id }),
        { review_note: reviewNote.value || undefined },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function submitCancel() {
    isSubmitting.value = true;

    router.patch(
        cancel.url({ approvalRequest: props.approval.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function goBack() {
    router.visit(props.backLink.href);
}
</script>

<template>
    <Head :title="props.approval.subject_name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <!-- Back nav -->
            <div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="gap-1.5 text-muted-foreground hover:text-foreground"
                    @click="goBack"
                >
                    <ArrowLeft class="size-3.5" />
                    {{ __(props.backLink.label) }}
                </Button>
            </div>

            <!-- Hero card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <div class="p-6 md:p-8">
                    <div
                        class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <!-- Identity -->
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant="outline"
                                    :class="`rounded-full text-xs ${statusConfig(props.approval.status).badgeClass}`"
                                >
                                    {{
                                        __(
                                            statusConfig(props.approval.status)
                                                .label,
                                        )
                                    }}
                                </Badge>
                                <Badge
                                    variant="secondary"
                                    class="rounded-full text-xs"
                                >
                                    {{ props.approval.action_label }}
                                </Badge>
                            </div>

                            <h1
                                class="text-2xl font-bold tracking-tight text-foreground"
                            >
                                {{ props.approval.subject_name }}
                            </h1>

                            <p class="text-sm text-muted-foreground">
                                {{ __('Requested by') }}
                                <span class="font-medium text-foreground">
                                    {{
                                        props.approval.requested_by_name ??
                                        __('Unknown')
                                    }}
                                </span>
                                <span
                                    class="mx-1.5 select-none"
                                    aria-hidden="true"
                                    >·</span
                                >
                                {{
                                    dateFormatter.format(
                                        new Date(props.approval.requested_at),
                                    )
                                }}
                            </p>
                        </div>

                        <!-- Decisions live below with full context and note entry -->
                    </div>
                </div>
            </div>

            <!-- Main content grid -->
            <div
                class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]"
            >
                <!-- Left: request details -->
                <div class="space-y-5">
                    <!-- Impact summary -->
                    <div
                        v-if="props.approval.impact_summary"
                        class="space-y-1 rounded-2xl border border-sidebar-border/70 bg-background/90 px-5 py-4"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            {{ __('Impact summary') }}
                        </p>
                        <p class="text-sm text-foreground">
                            {{ props.approval.impact_summary }}
                        </p>
                    </div>

                    <!-- Requester / reviewer block -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ __('Requested by') }}
                            </p>
                            <p
                                class="mt-0.5 text-sm font-medium text-foreground"
                            >
                                {{
                                    props.approval.requested_by_name ??
                                    __('Unknown')
                                }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{
                                    dateFormatter.format(
                                        new Date(props.approval.requested_at),
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            v-if="props.approval.reviewed_by_name"
                            class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ __('Reviewed by') }}
                            </p>
                            <p
                                class="mt-0.5 text-sm font-medium text-foreground"
                            >
                                {{ props.approval.reviewed_by_name }}
                            </p>
                            <p
                                v-if="props.approval.reviewed_at"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{
                                    dateFormatter.format(
                                        new Date(props.approval.reviewed_at),
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Request note -->
                    <div v-if="props.approval.request_note" class="space-y-1.5">
                        <p
                            class="px-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ __('Request note') }}
                        </p>
                        <p
                            class="rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-sm text-foreground"
                        >
                            {{ props.approval.request_note }}
                        </p>
                    </div>

                    <!-- Review note (already decided, read-only) -->
                    <div
                        v-if="
                            props.approval.review_note &&
                            !props.approval.can_approve &&
                            !props.approval.can_reject
                        "
                        class="space-y-1.5"
                    >
                        <p
                            class="px-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ __('Review note') }}
                        </p>
                        <p
                            class="rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-sm text-foreground"
                        >
                            {{ props.approval.review_note }}
                        </p>
                    </div>

                    <!-- Subject snapshot -->
                    <Collapsible
                        v-if="hasContent(props.approval.subject_snapshot)"
                        v-model:open="snapshotOpen"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center justify-between rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-left text-xs font-medium text-foreground transition-colors hover:bg-sidebar/20"
                        >
                            {{ __('Subject snapshot') }}
                            <component
                                :is="snapshotOpen ? ChevronUp : ChevronDown"
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <pre
                                class="mt-1 overflow-x-auto rounded-xl border border-sidebar-border/70 bg-sidebar/10 p-3 text-xs text-muted-foreground"
                                >{{
                                    formatJson(props.approval.subject_snapshot!)
                                }}</pre
                            >
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Before / after payloads -->
                    <template
                        v-if="
                            hasContent(props.approval.before_payload) ||
                            hasContent(props.approval.after_payload)
                        "
                    >
                        <Separator />

                        <Collapsible
                            v-if="hasContent(props.approval.before_payload)"
                            v-model:open="beforePayloadOpen"
                        >
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-left text-xs font-medium text-foreground transition-colors hover:bg-sidebar/20"
                            >
                                {{ __('Before') }}
                                <component
                                    :is="
                                        beforePayloadOpen
                                            ? ChevronUp
                                            : ChevronDown
                                    "
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <pre
                                    class="mt-1 overflow-x-auto rounded-xl border border-sidebar-border/70 bg-sidebar/10 p-3 text-xs text-muted-foreground"
                                    >{{
                                        formatJson(
                                            props.approval.before_payload!,
                                        )
                                    }}</pre
                                >
                            </CollapsibleContent>
                        </Collapsible>

                        <Collapsible
                            v-if="hasContent(props.approval.after_payload)"
                            v-model:open="afterPayloadOpen"
                        >
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-left text-xs font-medium text-foreground transition-colors hover:bg-sidebar/20"
                            >
                                {{ __('After') }}
                                <component
                                    :is="
                                        afterPayloadOpen
                                            ? ChevronUp
                                            : ChevronDown
                                    "
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <pre
                                    class="mt-1 overflow-x-auto rounded-xl border border-sidebar-border/70 bg-sidebar/10 p-3 text-xs text-muted-foreground"
                                    >{{
                                        formatJson(
                                            props.approval.after_payload!,
                                        )
                                    }}</pre
                                >
                            </CollapsibleContent>
                        </Collapsible>
                    </template>

                    <!-- Decision and cancellation actions -->
                    <div
                        v-if="
                            props.approval.can_approve ||
                            props.approval.can_reject ||
                            props.approval.can_cancel
                        "
                        class="space-y-1.5"
                    >
                        <Separator />
                        <template
                            v-if="
                                props.approval.can_approve ||
                                props.approval.can_reject
                            "
                        >
                            <Label
                                for="cumpu-approval-show-review-note"
                                class="text-sm"
                            >
                                {{ __('Review note') }}
                                <span class="text-muted-foreground">
                                    ({{ __('optional') }})
                                </span>
                            </Label>
                            <Textarea
                                id="cumpu-approval-show-review-note"
                                v-model="reviewNote"
                                :placeholder="
                                    __('Add a note for the requester…')
                                "
                                :rows="3"
                            />
                        </template>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <Button
                                v-if="props.approval.can_approve"
                                :disabled="isSubmitting"
                                class="bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                                @click="submitApprove"
                            >
                                <Spinner
                                    v-if="isSubmitting"
                                    class="mr-1.5 size-4"
                                />
                                {{
                                    isSubmitting ? __('Saving…') : __('Approve')
                                }}
                            </Button>
                            <Button
                                v-if="props.approval.can_reject"
                                variant="outline"
                                :disabled="isSubmitting"
                                class="text-destructive/80 hover:bg-destructive/10 hover:text-destructive"
                                @click="submitReject"
                            >
                                <Spinner
                                    v-if="isSubmitting"
                                    class="mr-1.5 size-4"
                                />
                                {{
                                    isSubmitting ? __('Saving…') : __('Reject')
                                }}
                            </Button>
                            <Button
                                v-if="props.approval.can_cancel"
                                variant="outline"
                                :disabled="isSubmitting"
                                class="text-destructive/80 hover:bg-destructive/10 hover:text-destructive"
                                @click="submitCancel"
                            >
                                <Spinner
                                    v-if="isSubmitting"
                                    class="mr-1.5 size-4"
                                />
                                {{
                                    isSubmitting
                                        ? __('Saving…')
                                        : __('Cancel request')
                                }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Right: workflow + history -->
                <div class="space-y-5">
                    <ApprovalAssignmentsCard :assignments="props.assignments" />
                    <ApprovalActivityHistory :history="props.history" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
