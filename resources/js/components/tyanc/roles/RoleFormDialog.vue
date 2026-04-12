<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, ExternalLink } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalReasonDialog from '@/components/cumpu/approvals/ApprovalReasonDialog.vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store, update } from '@/routes/tyanc/roles';
import type { RoleRow } from '@/types';
import type { GovernedActionState } from '@/types/cumpu';

type RoleFormFields = {
    name: string;
    level: number;
};

const props = defineProps<{
    open: boolean;
    editingRole?: RoleRow | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

const defaultForm = (): RoleFormFields => ({
    name: '',
    level: 10,
});

const form = ref<RoleFormFields>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

const isEditing = computed(() => Boolean(props.editingRole));
const title = computed(() =>
    isEditing.value ? __('Edit role') : __('New role'),
);

// ── Approval dialog state ─────────────────────────────────────────────────────

const approvalDialogOpen = ref(false);
const approvalNote = ref('');

const updateActionState = computed<GovernedActionState | undefined | null>(
    () => props.editingRole?.update_approval_state,
);

const updateNeedsApprovalDialog = computed<boolean>(() => {
    const s = updateActionState.value;
    if (!s) return false;
    return s.approval_enabled && !s.bypasses_for_actor && !s.has_usable_grant;
});

const updateBlockedByRequest = computed(() =>
    updateActionState.value?.has_blocking_request
        ? updateActionState.value.relevant_request
        : null,
);

const submissionBlockedVisible = ref(false);

watch(
    () => props.editingRole,
    (role) => {
        if (role) {
            form.value = { name: role.name, level: role.level };
        } else {
            form.value = defaultForm();
        }
        errors.value = {};
        submissionBlockedVisible.value = false;
        approvalNote.value = '';
    },
    { immediate: true },
);

watch(approvalDialogOpen, (isOpen) => {
    if (!isOpen) {
        approvalNote.value = '';
        errors.value = {
            ...errors.value,
            request_note: undefined,
            approval: undefined,
        };
    }
});

function close() {
    approvalNote.value = '';
    emit('update:open', false);
}

// ── Submit flow ───────────────────────────────────────────────────────────────

function handleSubmit() {
    submissionBlockedVisible.value = false;

    const isEdit = isEditing.value && props.editingRole;

    if (isEdit && updateNeedsApprovalDialog.value) {
        if (updateBlockedByRequest.value) {
            submissionBlockedVisible.value = true;
            return;
        }
        approvalDialogOpen.value = true;
        return;
    }

    doSubmit('');
}

function onApprovalConfirm() {
    approvalDialogOpen.value = false;
    doSubmit(approvalNote.value);
}

function doSubmit(note: string) {
    processing.value = true;
    errors.value = {};

    const isEdit = isEditing.value && props.editingRole;
    const url = isEdit
        ? update.url({ role: props.editingRole!.id })
        : store.url();
    const method = isEdit ? 'patch' : 'post';
    const payload = isEdit
        ? { ...form.value, request_note: note || undefined }
        : { ...form.value };

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => close(),
        onError: (responseErrors) => {
            errors.value = responseErrors as Partial<Record<string, string>>;
            if (responseErrors.request_note || responseErrors.approval) {
                approvalNote.value = note;
                approvalDialogOpen.value = true;
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? __('Update the role name and hierarchy level.')
                            : __(
                                  'Create a new role. Assign permissions separately after creation.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="handleSubmit">
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="role-name">{{ __('Role name') }}</Label>
                        <Input
                            id="role-name"
                            type="text"
                            placeholder="editor"
                            :model-value="form.name"
                            @update:model-value="form.name = String($event)"
                        />
                        <FormFieldSupport :error="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role-level">{{ __('Level') }}</Label>
                        <Input
                            id="role-level"
                            type="number"
                            min="1"
                            max="999"
                            :model-value="String(form.level)"
                            @update:model-value="form.level = Number($event)"
                        />
                        <FormFieldSupport
                            :hint="
                                __(
                                    'Higher levels represent higher hierarchy and wider access.',
                                )
                            "
                            :error="errors.level"
                        />
                    </div>

                    <!-- Blocked submission callout (edit only) -->
                    <div
                        v-if="
                            isEditing &&
                            submissionBlockedVisible &&
                            updateBlockedByRequest
                        "
                        class="flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 px-3 py-3 dark:border-amber-500/20 dark:bg-amber-500/[0.07]"
                    >
                        <Clock
                            class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <div class="min-w-0 flex-1 space-y-1">
                            <p
                                class="text-sm font-medium text-amber-900 dark:text-amber-200"
                            >
                                {{
                                    __(
                                        'An approval request for this action is already pending.',
                                    )
                                }}
                            </p>
                            <p
                                class="text-xs text-amber-700/80 dark:text-amber-300/80"
                            >
                                {{
                                    __(
                                        'You cannot submit a new request until the existing one is resolved.',
                                    )
                                }}
                            </p>
                        </div>
                        <a
                            v-if="updateBlockedByRequest.detail_url"
                            :href="updateBlockedByRequest.detail_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex shrink-0 items-center gap-1 rounded-lg border border-amber-200/80 bg-white/60 px-2.5 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100/60 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                        >
                            {{ __('View') }}
                            <ExternalLink class="size-3" />
                        </a>
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="close"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button :disabled="processing" @click="handleSubmit">
                    <Spinner v-if="processing" />
                    {{ isEditing ? __('Save changes') : __('Create role') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ApprovalReasonDialog
        v-model:open="approvalDialogOpen"
        v-model:note="approvalNote"
        :title="__('Save role')"
        :description="
            __(
                'This action requires approval. Explain why these changes should be approved.',
            )
        "
        :action-label="__('Submit for approval')"
        :loading="processing"
        :error="errors.request_note ?? errors.approval"
        :relevant-request="updateActionState?.relevant_request ?? null"
        @confirm="onApprovalConfirm"
        @cancel="approvalNote = ''"
    />
</template>
