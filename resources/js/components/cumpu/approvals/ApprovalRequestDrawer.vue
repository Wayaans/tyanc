<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRightLeft, ExternalLink } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ApprovalStatusBadge from '@/components/cumpu/approvals/ApprovalStatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
import {
    approve,
    cancel,
    reassign,
    reject,
    show,
} from '@/routes/cumpu/approvals';
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

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        reviewNote.value = '';
        isSubmitting.value = false;
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
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="flex w-full flex-col sm:max-w-lg">
            <SheetHeader class="shrink-0">
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
                class="flex-1 space-y-4 overflow-y-auto py-4"
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

                <!-- Impact summary -->
                <div
                    v-if="props.request.impact_summary"
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ __('Impact summary') }}
                    </p>
                    <p class="mt-1 text-sm text-foreground">
                        {{ props.request.impact_summary }}
                    </p>
                </div>

                <!-- Request note -->
                <div v-if="props.request.request_note" class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ __('Request note') }}
                    </p>
                    <p
                        class="rounded-xl border border-sidebar-border/70 bg-sidebar/10 px-4 py-3 text-sm text-foreground"
                    >
                        {{ props.request.request_note }}
                    </p>
                </div>

                <!-- Review note input -->
                <div
                    v-if="props.request.can_approve || props.request.can_reject"
                    class="space-y-1.5"
                >
                    <Separator />
                    <Label class="text-sm">
                        {{ __('Review note') }}
                        <span class="text-muted-foreground"
                            >({{ __('optional') }})</span
                        >
                    </Label>
                    <Textarea
                        v-model="reviewNote"
                        :placeholder="__('Add a note for the requester…')"
                        :rows="3"
                    />
                </div>
            </div>

            <SheetFooter class="shrink-0 flex-wrap gap-2">
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
                    {{ isSubmitting ? __('Saving…') : __('Approve') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
