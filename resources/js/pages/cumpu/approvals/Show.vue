<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    ChevronDown,
    ChevronUp,
    KeyRound,
    PackageCheck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ApprovalActivityHistory from '@/components/cumpu/approvals/ApprovalActivityHistory.vue';
import ApprovalAssignmentsCard from '@/components/cumpu/approvals/ApprovalAssignmentsCard.vue';
import ApprovalReassignDialog from '@/components/cumpu/approvals/ApprovalReassignDialog.vue';
import ApprovalStatusBadge from '@/components/cumpu/approvals/ApprovalStatusBadge.vue';
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
import type { ActivityRow, ApprovalRequestRow } from '@/types';
import type { ApprovalAssignmentRow, ReassignOption } from '@/types/cumpu';

const props = defineProps<{
    approval: ApprovalRequestRow;
    assignments: ApprovalAssignmentRow[];
    history: ActivityRow[];
    backLink: {
        label: string;
        href: string;
    };
    reassignOptions?: ReassignOption[];
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

function hasContent(obj: Record<string, unknown> | null | undefined): boolean {
    return obj !== null && obj !== undefined && Object.keys(obj).length > 0;
}

function formatJson(obj: Record<string, unknown>): string {
    return JSON.stringify(obj, null, 2);
}

const reviewNote = ref('');
const isSubmitting = ref(false);
const snapshotOpen = ref(false);
const reassignDialogOpen = ref(false);

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
                                <ApprovalStatusBadge
                                    :status="props.approval.status"
                                />
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
                    <!-- Grant lifecycle flow -->
                    <div
                        v-if="
                            props.approval.status === 'approved' ||
                            props.approval.status === 'consumed' ||
                            props.approval.status === 'expired' ||
                            props.approval.is_grant_usable ||
                            props.approval.is_grant_expired
                        "
                        class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background/90"
                    >
                        <div
                            class="border-b border-sidebar-border/70 px-4 py-2.5"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Grant lifecycle') }}
                            </p>
                        </div>
                        <ol class="flex flex-col gap-0 px-4 py-3">
                            <!-- Step 1: Approved -->
                            <li class="flex items-start gap-3">
                                <div
                                    class="relative mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 ring-1 ring-emerald-500/30"
                                >
                                    <KeyRound
                                        class="size-3 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <div
                                        v-if="
                                            props.approval.status ===
                                                'consumed' ||
                                            props.approval.status ===
                                                'expired' ||
                                            props.approval.is_grant_expired
                                        "
                                        class="absolute top-6 left-[11px] h-6 w-0.5 bg-emerald-200 dark:bg-emerald-800/60"
                                    />
                                </div>
                                <div class="min-w-0 flex-1 pb-4">
                                    <p
                                        class="text-sm font-medium text-emerald-800 dark:text-emerald-300"
                                    >
                                        {{ __('Grant issued') }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        <span
                                            v-if="
                                                props.approval.reviewed_by_name
                                            "
                                            >{{
                                                props.approval.reviewed_by_name
                                            }}</span
                                        >
                                        <template
                                            v-if="
                                                props.approval
                                                    .reviewed_by_name &&
                                                props.approval.reviewed_at
                                            "
                                        >
                                            <span
                                                class="mx-1"
                                                aria-hidden="true"
                                                >·</span
                                            >
                                        </template>
                                        <span
                                            v-if="props.approval.reviewed_at"
                                            >{{
                                                dateFormatter.format(
                                                    new Date(
                                                        props.approval
                                                            .reviewed_at,
                                                    ),
                                                )
                                            }}</span
                                        >
                                    </p>
                                    <p
                                        v-if="props.approval.expires_at"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ __('Valid until') }}:
                                        <span class="font-medium">{{
                                            dateFormatter.format(
                                                new Date(
                                                    props.approval.expires_at,
                                                ),
                                            )
                                        }}</span>
                                    </p>
                                </div>
                            </li>

                            <!-- Step 2a: Awaiting use (grant ready) -->
                            <li
                                v-if="props.approval.is_grant_usable"
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 ring-1 ring-emerald-400/40 ring-offset-1 ring-offset-background"
                                >
                                    <span
                                        class="size-2 animate-pulse rounded-full bg-emerald-500"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{
                                            __('Waiting for requester to retry')
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            __(
                                                'The requester must retry the action to consume the grant.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </li>

                            <!-- Step 2b: Consumed -->
                            <li
                                v-else-if="props.approval.status === 'consumed'"
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-500/15 ring-1 ring-violet-500/30"
                                >
                                    <PackageCheck
                                        class="size-3 text-violet-600 dark:text-violet-400"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-violet-800 dark:text-violet-300"
                                    >
                                        {{ __('Grant consumed') }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        <span
                                            v-if="
                                                props.approval.consumed_by_name
                                            "
                                            >{{
                                                props.approval.consumed_by_name
                                            }}</span
                                        >
                                        <template
                                            v-if="
                                                props.approval
                                                    .consumed_by_name &&
                                                props.approval.consumed_at
                                            "
                                        >
                                            <span
                                                class="mx-1"
                                                aria-hidden="true"
                                                >·</span
                                            >
                                        </template>
                                        <span
                                            v-if="props.approval.consumed_at"
                                            >{{
                                                dateFormatter.format(
                                                    new Date(
                                                        props.approval
                                                            .consumed_at,
                                                    ),
                                                )
                                            }}</span
                                        >
                                    </p>
                                </div>
                            </li>

                            <!-- Step 2c: Expired -->
                            <li
                                v-else-if="
                                    props.approval.status === 'expired' ||
                                    props.approval.is_grant_expired
                                "
                                class="flex items-start gap-3"
                            >
                                <div
                                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-500/15 ring-1 ring-amber-500/30"
                                >
                                    <KeyRound
                                        class="size-3 text-amber-600 dark:text-amber-400"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-medium text-amber-800 dark:text-amber-300"
                                    >
                                        {{ __('Grant expired') }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            __(
                                                'Not used before expiry. A new approval request is required.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="props.approval.expires_at"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ __('Expired') }}:
                                        <span class="font-medium">{{
                                            dateFormatter.format(
                                                new Date(
                                                    props.approval.expires_at,
                                                ),
                                            )
                                        }}</span>
                                    </p>
                                </div>
                            </li>
                        </ol>
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

                    <!-- Request reason -->
                    <div v-if="props.approval.request_note" class="space-y-1.5">
                        <div class="flex items-baseline gap-1.5">
                            <p
                                class="px-0.5 text-sm font-semibold text-foreground"
                            >
                                {{ __('Reason for this request') }}
                            </p>
                            <p
                                v-if="props.approval.requested_by_name"
                                class="text-xs text-muted-foreground"
                            >
                                – {{ props.approval.requested_by_name }}
                            </p>
                        </div>
                        <p
                            class="rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-sm leading-relaxed text-foreground"
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
                            <span>
                                {{ __('Snapshot at request time') }}
                                <span
                                    class="ml-1 font-normal text-muted-foreground"
                                >
                                    –
                                    {{ __('subject state when submitted') }}
                                </span>
                            </span>
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
                            <p
                                v-if="props.approval.can_approve"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    __(
                                        'Approving issues a one-time grant. The requester must retry the action to consume it.',
                                    )
                                }}
                            </p>
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
                                    isSubmitting
                                        ? __('Saving…')
                                        : __('Approve & issue grant')
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

                    <!-- Reassign action (only when permitted) -->
                    <div
                        v-if="
                            props.approval.can_reassign &&
                            props.reassignOptions?.length
                        "
                        class="rounded-2xl border border-sidebar-border/70 bg-background/90 px-5 py-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <p class="text-sm font-medium text-foreground">
                                    {{ __('Reassign') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        __(
                                            'Redirect pending steps to different reviewers.',
                                        )
                                    }}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="shrink-0 gap-1.5"
                                @click="reassignDialogOpen = true"
                            >
                                <ArrowRightLeft class="size-3.5" />
                                {{ __('Reassign') }}
                            </Button>
                        </div>
                    </div>

                    <ApprovalActivityHistory
                        :history="props.history"
                        :approval="props.approval"
                    />
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Reassign dialog -->
    <ApprovalReassignDialog
        v-model:open="reassignDialogOpen"
        :request="props.approval"
        :reassign-options="props.reassignOptions ?? []"
    />
</template>
