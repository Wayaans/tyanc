<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRightLeft,
    ExternalLink,
    KeyRound,
    PackageCheck,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ApprovalStatusBadge from '@/components/cumpu/approvals/ApprovalStatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';
import { approve, cancel, reject, show } from '@/routes/cumpu/approvals';
import type { ApprovalRequestRow } from '@/types';

const props = defineProps<{
    request: ApprovalRequestRow | null;
}>();

const emit = defineEmits<{
    reassign: [request: ApprovalRequestRow];
    closed: [];
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

watch(open, (isOpen) => {
    if (!isOpen) {
        reviewNote.value = '';
        isSubmitting.value = false;
        snapshotOpen.value = false;
        emit('closed');
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

function openReassign() {
    if (props.request) {
        emit('reassign', props.request);
        open.value = false;
    }
}

function hasContent(obj: Record<string, unknown> | null | undefined): boolean {
    return obj !== null && obj !== undefined && Object.keys(obj).length > 0;
}

function formatJson(obj: Record<string, unknown>): string {
    return JSON.stringify(obj, null, 2);
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="flex w-full flex-col sm:max-w-lg">
            <SheetHeader
                class="shrink-0 border-b border-sidebar-border/70 px-6 pt-6 pb-5"
            >
                <SheetTitle class="truncate pr-8">
                    {{ props.request?.subject_name ?? __('Approval request') }}
                </SheetTitle>
                <SheetDescription
                    v-if="props.request"
                    class="flex flex-wrap items-center gap-2"
                >
                    <ApprovalStatusBadge :status="props.request.status" />
                    <Badge variant="secondary" class="rounded-full text-xs">
                        {{ props.request.action_label }}
                    </Badge>
                    <Badge
                        v-if="props.request.is_escalated"
                        variant="outline"
                        class="rounded-full border-amber-500/30 bg-amber-500/10 text-xs text-amber-700 dark:text-amber-300"
                    >
                        <AlertTriangle class="mr-0.5 size-2.5" />
                        {{ __('Escalated') }}
                    </Badge>
                    <Badge
                        v-if="props.request.is_reassigned"
                        variant="outline"
                        class="rounded-full border-violet-500/30 bg-violet-500/10 text-xs text-violet-700 dark:text-violet-300"
                    >
                        <ArrowRightLeft class="mr-0.5 size-2.5" />
                        {{ __('Reassigned') }}
                    </Badge>
                </SheetDescription>
            </SheetHeader>

            <div
                v-if="props.request"
                class="flex-1 space-y-4 overflow-y-auto px-6 py-5"
            >
                <!-- Meta grid -->
                <div class="grid gap-3 sm:grid-cols-2">
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
                        v-if="props.request.pending_assignee_names?.length"
                        class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ __('Current assignees') }}
                        </p>
                        <p class="mt-0.5 text-sm font-medium text-foreground">
                            {{
                                props.request.pending_assignee_names.join(', ')
                            }}
                        </p>
                        <p
                            v-if="props.request.current_step_label"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ props.request.current_step_label }}
                        </p>
                    </div>
                </div>

                <!-- Timing flags -->
                <div
                    v-if="
                        props.request.escalated_at ||
                        props.request.last_reassigned_at
                    "
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <div
                        v-if="props.request.escalated_at"
                        class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3"
                    >
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            {{ __('Escalated at') }}
                        </p>
                        <p class="mt-0.5 text-sm font-medium text-foreground">
                            {{
                                dateFormatter.format(
                                    new Date(props.request.escalated_at),
                                )
                            }}
                        </p>
                    </div>
                    <div
                        v-if="props.request.last_reassigned_at"
                        class="rounded-xl border border-violet-500/20 bg-violet-500/5 px-4 py-3"
                    >
                        <p class="text-xs text-violet-700 dark:text-violet-300">
                            {{ __('Last reassigned') }}
                        </p>
                        <p class="mt-0.5 text-sm font-medium text-foreground">
                            {{
                                dateFormatter.format(
                                    new Date(props.request.last_reassigned_at),
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Grant lifecycle flow -->
                <div
                    v-if="
                        props.request.status === 'approved' ||
                        props.request.status === 'consumed' ||
                        props.request.status === 'expired' ||
                        props.request.is_grant_usable ||
                        props.request.is_grant_expired
                    "
                    class="overflow-hidden rounded-xl border border-sidebar-border/70"
                >
                    <div class="border-b border-sidebar-border/70 px-4 py-2">
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Grant lifecycle') }}
                        </p>
                    </div>
                    <ol class="flex flex-col gap-0 px-4 py-3">
                        <!-- Issued -->
                        <li class="flex items-start gap-3">
                            <div
                                class="relative mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 ring-1 ring-emerald-500/30"
                            >
                                <KeyRound
                                    class="size-3 text-emerald-600 dark:text-emerald-400"
                                />
                                <div
                                    v-if="
                                        props.request.status === 'consumed' ||
                                        props.request.status === 'expired' ||
                                        props.request.is_grant_expired
                                    "
                                    class="absolute top-6 left-[11px] h-5 w-0.5 bg-emerald-200 dark:bg-emerald-800/60"
                                />
                            </div>
                            <div class="min-w-0 flex-1 pb-3">
                                <p
                                    class="text-sm font-medium text-emerald-800 dark:text-emerald-300"
                                >
                                    {{ __('Grant issued') }}
                                </p>
                                <p
                                    v-if="props.request.expires_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ __('Valid until') }}:
                                    {{
                                        dateFormatter.format(
                                            new Date(props.request.expires_at),
                                        )
                                    }}
                                </p>
                            </div>
                        </li>

                        <!-- Awaiting retry -->
                        <li
                            v-if="props.request.is_grant_usable"
                            class="flex items-start gap-3"
                        >
                            <div
                                class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 ring-1 ring-emerald-400/40"
                            >
                                <span
                                    class="size-2 animate-pulse rounded-full bg-emerald-500"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-medium text-emerald-700 dark:text-emerald-400"
                                >
                                    {{ __('Waiting for requester to retry') }}
                                </p>
                            </div>
                        </li>

                        <!-- Consumed -->
                        <li
                            v-else-if="props.request.status === 'consumed'"
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
                                <p
                                    v-if="
                                        props.request.consumed_by_name ||
                                        props.request.consumed_at
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    <span
                                        v-if="props.request.consumed_by_name"
                                        >{{
                                            props.request.consumed_by_name
                                        }}</span
                                    >
                                    <span
                                        v-if="
                                            props.request.consumed_by_name &&
                                            props.request.consumed_at
                                        "
                                        class="mx-1"
                                        aria-hidden="true"
                                        >·</span
                                    >
                                    <span v-if="props.request.consumed_at">{{
                                        dateFormatter.format(
                                            new Date(props.request.consumed_at),
                                        )
                                    }}</span>
                                </p>
                            </div>
                        </li>

                        <!-- Expired -->
                        <li
                            v-else-if="
                                props.request.status === 'expired' ||
                                props.request.is_grant_expired
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
                                <p
                                    v-if="props.request.expires_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        dateFormatter.format(
                                            new Date(props.request.expires_at),
                                        )
                                    }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Requester's reason -->
                <div v-if="props.request.request_note" class="space-y-1.5">
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ __('Request reason') }}
                    </p>
                    <p
                        class="rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-sm leading-relaxed text-foreground"
                    >
                        {{ props.request.request_note }}
                    </p>
                    <p
                        v-if="props.request.requested_by_name"
                        class="text-xs text-muted-foreground"
                    >
                        — {{ props.request.requested_by_name }}
                    </p>
                </div>

                <!-- Review note (already set, read-only) -->
                <div
                    v-if="
                        props.request.review_note &&
                        !props.request.can_approve &&
                        !props.request.can_reject
                    "
                    class="space-y-1.5"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ __('Review note') }}
                    </p>
                    <p
                        class="rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-sm text-foreground"
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
                        <span class="text-muted-foreground">
                            {{ snapshotOpen ? __('Hide') : __('Show') }}
                        </span>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <pre
                            class="mt-1 overflow-x-auto rounded-xl border border-sidebar-border/70 bg-sidebar/10 p-3 text-xs text-muted-foreground"
                            >{{
                                formatJson(props.request.subject_snapshot!)
                            }}</pre
                        >
                    </CollapsibleContent>
                </Collapsible>

                <!-- Review note input -->
                <div
                    v-if="props.request.can_approve || props.request.can_reject"
                    class="space-y-3"
                >
                    <Separator />
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ __('Review note') }}
                        <span class="ml-1 font-normal normal-case"
                            >({{ __('optional') }})</span
                        >
                    </p>
                    <Textarea
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

            <SheetFooter
                class="shrink-0 flex-row flex-wrap border-t border-sidebar-border/70 px-6 py-4"
            >
                <!-- View full detail -->
                <Button
                    v-if="props.request"
                    variant="ghost"
                    size="sm"
                    class="mr-auto gap-1.5"
                    as-child
                >
                    <TextLink
                        :href="show.url({ approvalRequest: props.request.id })"
                    >
                        <ExternalLink class="size-3.5" />
                        {{ __('Full detail') }}
                    </TextLink>
                </Button>

                <!-- Reassign -->
                <Button
                    v-if="props.request?.can_reassign"
                    variant="outline"
                    size="sm"
                    :disabled="isSubmitting"
                    class="gap-1.5"
                    @click="openReassign"
                >
                    <ArrowRightLeft class="size-3.5" />
                    {{ __('Reassign') }}
                </Button>

                <!-- Cancel request -->
                <Button
                    v-if="props.request?.can_cancel"
                    variant="outline"
                    size="sm"
                    :disabled="isSubmitting"
                    class="text-destructive/80 hover:bg-destructive/10 hover:text-destructive"
                    @click="submitCancel"
                >
                    <Spinner v-if="isSubmitting" class="size-3.5" />
                    {{ __('Cancel request') }}
                </Button>

                <!-- Reject -->
                <Button
                    v-if="props.request?.can_reject"
                    variant="outline"
                    size="sm"
                    :disabled="isSubmitting"
                    class="text-destructive/80 hover:bg-destructive/10 hover:text-destructive"
                    @click="submitReject"
                >
                    <Spinner v-if="isSubmitting" class="size-3.5" />
                    {{ __('Reject') }}
                </Button>

                <!-- Approve -->
                <Button
                    v-if="props.request?.can_approve"
                    size="sm"
                    :disabled="isSubmitting"
                    class="bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                    @click="submitApprove"
                >
                    <Spinner v-if="isSubmitting" class="size-3.5" />
                    {{
                        isSubmitting
                            ? __('Saving…')
                            : __('Approve & issue grant')
                    }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
