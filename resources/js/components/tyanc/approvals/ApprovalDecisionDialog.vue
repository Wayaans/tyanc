<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';
import { approve, reject } from '@/routes/tyanc/users/approvals';
import type { ApprovalRequestRow } from '@/types';

const props = defineProps<{
    request: ApprovalRequestRow | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const { __ } = useTranslations();

const reviewNote = ref('');
const isSubmitting = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) {
        reviewNote.value = '';
        isSubmitting.value = false;
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
            onFinish: () => {
                isSubmitting.value = false;
                open.value = false;
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
            onFinish: () => {
                isSubmitting.value = false;
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ __('Approval request') }}</DialogTitle>
                <DialogDescription>
                    {{ props.request?.subject_name }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="props.request" class="space-y-4">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ __('Requested by') }}
                    </p>
                    <p class="mt-0.5 text-sm font-medium text-foreground">
                        {{ props.request.requested_by_name ?? __('Unknown') }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="approval-review-note" class="text-sm">
                        {{ __('Review note') }}
                    </Label>
                    <Textarea
                        id="approval-review-note"
                        v-model="reviewNote"
                        :placeholder="__('Review note')"
                        rows="4"
                    />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    variant="outline"
                    :disabled="isSubmitting"
                    @click="open = false"
                >
                    {{ __('Cancel') }}
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
                    {{ isSubmitting ? __('Saving…') : __('Approve') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
