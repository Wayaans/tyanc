<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    KeyRound,
    PackageCheck,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import DialogScrollContent from '@/components/ui/dialog/DialogScrollContent.vue';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';
import { approve, cancel, reject } from '@/routes/cumpu/approvals';
import type { ApprovalRequestRow, ApprovalStatus } from '@/types';

const props = defineProps<{
    request: ApprovalRequestRow | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const { __ } = useTranslations();

const reviewNote = ref('');
const isSubmitting = ref(false);
const snapshotOpen = ref(false);

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

type StatusConfig = {
    label: string;
    badgeClass: string;
};

const statusConfigs: Record<ApprovalStatus, StatusConfig> = {
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
    consumed: {
        label: 'Consumed',
        badgeClass:
            'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300',
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

watch(open, (isOpen) => {
    if (!isOpen) {
        reviewNote.value = '';
        isSubmitting.value = false;
        snapshotOpen.value = false;
    }
});

function submitApprove() {
    if (!props.request) {
        return;
    }

    isSubmitting.value = true;

    router.patch(
        approve.url({ approvalRequest: props.request.id }),
        { review_note: reviewNote.value || undefined },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function submitReject() {
    if (!props.request) {
        return;
    }

    isSubmitting.value = true;

    router.patch(
        reject.url({ approvalRequest: props.request.id }),
        { review_note: reviewNote.value || undefined },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function submitCancel() {
    if (!props.request) {
        return;
    }

    isSubmitting.value = true;

    router.patch(
        cancel.url({ approvalRequest: props.request.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ __('Approval request') }}</DialogTitle>
                <DialogDescription>
                    {{ props.request?.subject_name }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="props.request" class="space-y-4">
                <!-- Status + action badge row -->
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        :class="`rounded-full text-xs ${statusConfig(props.request.status).badgeClass}`"
                    >
                        {{ __(statusConfig(props.request.status).label) }}
                    </Badge>
                    <Badge variant="secondary" class="rounded-full text-xs">
                        {{ props.request.action_label }}
                    </Badge>
                </div>

                <!-- Grant lifecycle callout -->
                <!-- Approved: grant issued, waiting for requester to retry -->
                <div
                    v-if="
                        props.request.status === 'approved' &&
                        props.request.is_grant_usable
                    "
                    class="flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-3"
                >
                    <KeyRound
                        class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <div class="space-y-0.5">
                        <p
                            class="text-sm font-medium text-emerald-800 dark:text-emerald-300"
                        >
                            {{ __('Grant issued') }}
                        </p>
                        <p
                            class="text-xs text-emerald-700 dark:text-emerald-400"
                        >
                            {{
                                __(
                                    'A one-time grant has been issued. The requester must retry the action to consume it.',
                                )
                            }}
                        </p>
                        <p
                            v-if="props.request.expires_at"
                            class="text-xs text-emerald-700 dark:text-emerald-400"
                        >
                            {{ __('Expires') }}:
                            {{
                                dateFormatter.format(
                                    new Date(props.request.expires_at),
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Consumed: grant was used -->
                <div
                    v-if="props.request.status === 'consumed'"
                    class="flex items-start gap-3 rounded-xl border border-violet-500/30 bg-violet-500/5 px-4 py-3"
                >
                    <PackageCheck
                        class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-400"
                    />
                    <div class="space-y-0.5">
                        <p
                            class="text-sm font-medium text-violet-800 dark:text-violet-300"
                        >
                            {{ __('Grant consumed') }}
                        </p>
                        <p class="text-xs text-violet-700 dark:text-violet-400">
                            {{
                                __(
                                    'The requester retried the action and the grant was used.',
                                )
                            }}
                        </p>
                        <p
                            v-if="
                                props.request.consumed_by_name ||
                                props.request.consumed_at
                            "
                            class="text-xs text-violet-700 dark:text-violet-400"
                        >
                            <span v-if="props.request.consumed_by_name">
                                {{ props.request.consumed_by_name }}
                            </span>
                            <span
                                v-if="
                                    props.request.consumed_by_name &&
                                    props.request.consumed_at
                                "
                                class="mx-1"
                                aria-hidden="true"
                                >·</span
                            >
                            <span v-if="props.request.consumed_at">
                                {{
                                    dateFormatter.format(
                                        new Date(props.request.consumed_at),
                                    )
                                }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Expired: grant was never used in time -->
                <div
                    v-if="
                        props.request.status === 'expired' ||
                        (props.request.status === 'approved' &&
                            props.request.is_grant_expired)
                    "
                    class="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3"
                >
                    <KeyRound
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <div class="space-y-0.5">
                        <p
                            class="text-sm font-medium text-amber-800 dark:text-amber-300"
                        >
                            {{ __('Grant expired') }}
                        </p>
                        <p class="text-xs text-amber-700 dark:text-amber-400">
                            {{
                                __(
                                    'The grant was not used before its expiry. A new approval request is required.',
                                )
                            }}
                        </p>
                        <p
                            v-if="props.request.expires_at"
                            class="text-xs text-amber-700 dark:text-amber-400"
                        >
                            {{
                                dateFormatter.format(
                                    new Date(props.request.expires_at),
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Requester / reviewer block -->
                <div class="grid grid-cols-2 gap-3">
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ __('Requested by') }}
                        </p>
                        <p class="mt-0.5 text-sm font-medium text-foreground">
                            {{
                                props.request.requested_by_name ?? __('Unknown')
                            }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                dateFormatter.format(
                                    new Date(props.request.requested_at),
                                )
                            }}
                        </p>
                    </div>

                    <div
                        v-if="props.request.reviewed_by_name"
                        class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ __('Reviewed by') }}
                        </p>
                        <p class="mt-0.5 text-sm font-medium text-foreground">
                            {{ props.request.reviewed_by_name }}
                        </p>
                        <p
                            v-if="props.request.reviewed_at"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{
                                dateFormatter.format(
                                    new Date(props.request.reviewed_at),
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Request note -->
                <div v-if="props.request.request_note" class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ __('Request note') }}
                    </p>
                    <p
                        class="rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2 text-sm text-foreground"
                    >
                        {{ props.request.request_note }}
                    </p>
                </div>

                <!-- Review note (already set, read-only) -->
                <div
                    v-if="
                        props.request.review_note &&
                        !props.request.can_approve &&
                        !props.request.can_reject
                    "
                    class="space-y-1"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ __('Review note') }}
                    </p>
                    <p
                        class="rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2 text-sm text-foreground"
                    >
                        {{ props.request.review_note }}
                    </p>
                </div>

                <!-- Subject snapshot -->
                <Collapsible
                    v-if="hasContent(props.request.subject_snapshot)"
                    v-model:open="snapshotOpen"
                >
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2 text-left text-xs font-medium text-foreground transition-colors hover:bg-sidebar/20"
                    >
                        {{ __('Subject snapshot') }}
                        <component
                            :is="snapshotOpen ? ChevronUp : ChevronDown"
                            class="size-3.5 shrink-0 text-muted-foreground"
                        />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <pre
                            class="mt-1 overflow-x-auto rounded-lg border border-sidebar-border/70 bg-sidebar/10 p-3 text-xs text-muted-foreground"
                            >{{
                                formatJson(props.request.subject_snapshot!)
                            }}</pre
                        >
                    </CollapsibleContent>
                </Collapsible>

                <!-- Review note input (only when reviewer can decide) -->
                <div
                    v-if="props.request.can_approve || props.request.can_reject"
                    class="space-y-1.5"
                >
                    <Separator />
                    <Label for="approval-review-note" class="text-sm">
                        {{ __('Review note') }}
                        <span class="text-muted-foreground">
                            ({{ __('optional') }})
                        </span>
                    </Label>
                    <Textarea
                        id="approval-review-note"
                        v-model="reviewNote"
                        :placeholder="__('Add a note for the requester…')"
                        :rows="3"
                    />
                    <p
                        v-if="props.request.can_approve"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            __(
                                'Approving issues a one-time grant. The requester must retry the action to consume it.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    variant="outline"
                    :disabled="isSubmitting"
                    @click="open = false"
                >
                    {{ __('Close') }}
                </Button>

                <Button
                    v-if="props.request?.can_cancel"
                    variant="outline"
                    :disabled="isSubmitting"
                    class="text-destructive/80 hover:bg-destructive/10 hover:text-destructive"
                    @click="submitCancel"
                >
                    {{ isSubmitting ? __('Saving…') : __('Cancel request') }}
                </Button>

                <Button
                    v-if="props.request?.can_reject"
                    variant="outline"
                    :disabled="isSubmitting"
                    class="text-destructive/80 hover:bg-destructive/10 hover:text-destructive"
                    @click="submitReject"
                >
                    {{ __('Reject') }}
                </Button>

                <Button
                    v-if="props.request?.can_approve"
                    :disabled="isSubmitting"
                    class="bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                    @click="submitApprove"
                >
                    {{
                        isSubmitting
                            ? __('Saving…')
                            : __('Approve & issue grant')
                    }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
