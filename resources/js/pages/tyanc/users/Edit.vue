<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    ExternalLink,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalHistoryPanel from '@/components/cumpu/approvals/ApprovalHistoryPanel.vue';
import ApprovalReasonDialog from '@/components/cumpu/approvals/ApprovalReasonDialog.vue';
import ApprovalRequestBanner from '@/components/cumpu/approvals/ApprovalRequestBanner.vue';
import UserForm, {
    type UserEditorFields,
} from '@/components/tyanc/users/UserForm.vue';
import UserStatusBadge from '@/components/tyanc/users/UserStatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { destroy, show, suspend, update } from '@/routes/tyanc/users';
import type {
    PermissionOption,
    RoleOption,
    SelectOption,
    UserFormData,
} from '@/types';
import type { ApprovalContext, GovernedActionState } from '@/types/cumpu';

const props = defineProps<{
    user: UserFormData;
    roles: RoleOption[];
    permissions: PermissionOption[];
    locales: SelectOption[];
    statuses: SelectOption[];
    timezones: string[];
    approvalContext?: ApprovalContext | null;
    status?: string | null;
}>();

const { __ } = useTranslations();
const { usersEditBreadcrumbs } = useAppNavigation();

const breadcrumbs = usersEditBreadcrumbs(props.user.name, props.user.id);

function fromUserFormData(user: UserFormData): UserEditorFields {
    return {
        name: user.name,
        username: user.username ?? '',
        email: user.email,
        avatar: null,
        remove_avatar: false,
        status: user.status,
        locale: user.locale,
        timezone: user.timezone,
        roles: [...user.roles],
        permissions: [...user.permissions],
        password: '',
        password_confirmation: '',
    };
}

const form = ref<UserEditorFields>(fromUserFormData(props.user));
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const suspendProcessing = ref(false);
const deleteProcessing = ref(false);
const deleteErrors = ref<Partial<Record<string, string>>>({});
const confirmingDelete = ref(false);

// ── Approval dialog state ─────────────────────────────────────────────────────

const approvalDialogOpen = ref(false);
const approvalNote = ref('');

/** The update governed-action state from the approval context, if present. */
const updateActionState = computed<GovernedActionState | undefined>(
    () => props.approvalContext?.governed_actions?.['update'],
);

/**
 * Returns true when the update must go through the approval-request branch.
 * A blocking request may still short-circuit this into an inline explainer.
 */
const updateNeedsApprovalDialog = computed<boolean>(() => {
    const s = updateActionState.value;
    if (!s) return false;
    return s.approval_enabled && !s.bypasses_for_actor && !s.has_usable_grant;
});

/**
 * When there is already a blocking approval request for the update action,
 * clicking Save should surface this info rather than opening a new request flow.
 */
const updateBlockedByRequest = computed(() =>
    updateActionState.value?.has_blocking_request
        ? updateActionState.value.relevant_request
        : null,
);

/** Shown inline when the user tries to submit while blocked. */
const submissionBlockedVisible = ref(false);

const deleteApprovalDialogOpen = ref(false);
const deleteApprovalNote = ref('');

const deleteActionState = computed<GovernedActionState | undefined>(
    () => props.approvalContext?.governed_actions?.['delete'],
);

const deleteNeedsApprovalDialog = computed<boolean>(() => {
    const s = deleteActionState.value;
    if (!s) return false;
    return s.approval_enabled && !s.bypasses_for_actor && !s.has_usable_grant;
});

const deleteBlockedByRequest = computed(() =>
    deleteActionState.value?.has_blocking_request
        ? deleteActionState.value.relevant_request
        : null,
);

const deleteBlockedVisible = ref(false);

watch(approvalDialogOpen, (isOpen) => {
    if (!isOpen) {
        approvalNote.value = '';
        errors.value = { ...errors.value, request_note: undefined };
    }
});

watch(deleteApprovalDialogOpen, (isOpen) => {
    if (!isOpen) {
        deleteApprovalNote.value = '';
        deleteErrors.value = { ...deleteErrors.value, request_note: undefined };
    }
});

// ── Navigation helpers ────────────────────────────────────────────────────────

function goBack() {
    router.visit(show.url({ user: props.user.id }));
}

// ── Submit flow ───────────────────────────────────────────────────────────────

function handleSubmit() {
    submissionBlockedVisible.value = false;

    if (updateNeedsApprovalDialog.value) {
        if (updateBlockedByRequest.value) {
            submissionBlockedVisible.value = true;

            return;
        }

        approvalDialogOpen.value = true;

        return;
    }

    // Has a usable grant, approval not required, or bypassed — submit directly.
    doSubmit('');
}

function onApprovalConfirm() {
    approvalDialogOpen.value = false;
    doSubmit(approvalNote.value);
}

function doSubmit(note: string) {
    processing.value = true;
    errors.value = {};

    router.post(
        update.url({ user: props.user.id }),
        {
            _method: 'PATCH',
            ...form.value,
            request_note: note || undefined,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (responseErrors) => {
                errors.value = responseErrors as Partial<
                    Record<string, string>
                >;

                // Re-open the approval dialog so the user can fix the note.
                if (responseErrors.request_note) {
                    approvalNote.value = note;
                    approvalDialogOpen.value = true;
                }
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

// ── Suspend / delete ──────────────────────────────────────────────────────────

function suspendUser() {
    suspendProcessing.value = true;

    router.patch(
        suspend.url({ user: props.user.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                suspendProcessing.value = false;
            },
        },
    );
}

function handleDelete() {
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;
        deleteBlockedVisible.value = false;

        return;
    }

    deleteBlockedVisible.value = false;

    if (deleteNeedsApprovalDialog.value) {
        if (deleteBlockedByRequest.value) {
            deleteBlockedVisible.value = true;

            return;
        }

        deleteApprovalDialogOpen.value = true;

        return;
    }

    doDelete('');
}

function onDeleteApprovalConfirm() {
    deleteApprovalDialogOpen.value = false;
    doDelete(deleteApprovalNote.value);
}

function doDelete(note: string) {
    deleteProcessing.value = true;
    deleteErrors.value = {};

    router.delete(destroy.url({ user: props.user.id }), {
        data: { request_note: note || undefined },
        // Server handles navigation after delete (or approval redirect).
        onError: (responseErrors) => {
            deleteErrors.value = responseErrors as Partial<
                Record<string, string>
            >;

            if (responseErrors.request_note) {
                deleteApprovalNote.value = note;
                deleteApprovalDialogOpen.value = true;
            }
        },
        onFinish: () => {
            deleteProcessing.value = false;
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <Head :title="__('Edit :name', { name: props.user.name })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <!-- Page header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    :aria-label="__('Back to user details')"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                </Button>
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <Avatar class="size-9 shrink-0">
                        <AvatarImage
                            :src="props.user.avatar ?? ''"
                            :alt="props.user.name"
                        />
                        <AvatarFallback class="text-xs">
                            {{ getInitials(props.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 space-y-0.5">
                        <h1
                            class="truncate text-xl font-semibold tracking-tight text-foreground"
                        >
                            {{ __('Edit :name', { name: props.user.name }) }}
                        </h1>
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm text-muted-foreground">
                                {{ props.user.email }}
                            </p>
                            <UserStatusBadge :status="props.user.status" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status feedback (e.g. approval submitted) -->
            <div
                v-if="props.status"
                class="flex items-start gap-3 rounded-xl border border-emerald-200/60 bg-emerald-50/50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/[0.07]"
            >
                <CheckCircle2
                    class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
                <p class="text-sm text-emerald-800 dark:text-emerald-200">
                    {{ props.status }}
                </p>
            </div>

            <!-- Approval banner -->
            <ApprovalRequestBanner
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />

            <!-- Form card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <form
                    class="space-y-6 p-6 md:p-8"
                    @submit.prevent="handleSubmit"
                >
                    <UserForm
                        v-model="form"
                        :errors="errors"
                        :roles="props.roles"
                        :permissions="props.permissions"
                        :locales="props.locales"
                        :statuses="props.statuses"
                        :timezones="props.timezones"
                        :current-avatar-url="props.user.avatar"
                        show-password-fields
                        password-optional
                    />

                    <Separator />

                    <!-- Blocked submission callout -->
                    <div
                        v-if="
                            submissionBlockedVisible && updateBlockedByRequest
                        "
                        class="flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/[0.07]"
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
                            {{ __('View request') }}
                            <ExternalLink class="size-3" />
                        </a>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="goBack"
                        >
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            {{ __('Save changes') }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Approval history -->
            <ApprovalHistoryPanel
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />

            <!-- Danger zone card -->
            <div
                class="overflow-hidden rounded-2xl border border-destructive/20 bg-background/90"
            >
                <div class="p-6 md:p-8">
                    <h2 class="text-sm font-semibold text-foreground">
                        {{ __('Account actions') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            __(
                                'These actions are irreversible or have significant consequences.',
                            )
                        }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="
                                suspendProcessing ||
                                processing ||
                                props.user.status === 'suspended'
                            "
                            @click="suspendUser"
                        >
                            <Spinner v-if="suspendProcessing" />
                            {{ __('Suspend account') }}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :class="[
                                confirmingDelete
                                    ? 'border-destructive text-destructive hover:bg-destructive/10'
                                    : 'text-destructive/80 hover:border-destructive hover:text-destructive',
                            ]"
                            :disabled="deleteProcessing || processing"
                            @click="handleDelete"
                        >
                            <AlertTriangle
                                v-if="confirmingDelete"
                                class="size-4"
                            />
                            <Spinner v-else-if="deleteProcessing" />
                            {{
                                confirmingDelete
                                    ? __('Click again to confirm deletion')
                                    : __('Delete this account')
                            }}
                        </Button>

                        <Button
                            v-if="confirmingDelete"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="
                                confirmingDelete = false;
                                deleteBlockedVisible = false;
                            "
                        >
                            {{ __('Cancel') }}
                        </Button>
                    </div>

                    <div
                        v-if="deleteBlockedVisible && deleteBlockedByRequest"
                        class="mt-4 flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/[0.07]"
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
                            v-if="deleteBlockedByRequest.detail_url"
                            :href="deleteBlockedByRequest.detail_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex shrink-0 items-center gap-1 rounded-lg border border-amber-200/80 bg-white/60 px-2.5 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100/60 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                        >
                            {{ __('View request') }}
                            <ExternalLink class="size-3" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Approval reason dialog for update submissions -->
    <ApprovalReasonDialog
        v-model:open="approvalDialogOpen"
        v-model:note="approvalNote"
        :title="__('Save changes')"
        :description="
            __(
                'This action requires approval. Explain why these changes should be approved.',
            )
        "
        :action-label="__('Submit for approval')"
        :loading="processing"
        :error="errors.request_note"
        :relevant-request="updateActionState?.relevant_request ?? null"
        @confirm="onApprovalConfirm"
        @cancel="approvalNote = ''"
    />

    <ApprovalReasonDialog
        v-model:open="deleteApprovalDialogOpen"
        v-model:note="deleteApprovalNote"
        :title="__('Delete user')"
        :description="
            __(
                'This action requires approval. Explain why this user should be deleted.',
            )
        "
        :action-label="__('Submit for approval')"
        :loading="deleteProcessing"
        :error="deleteErrors.request_note"
        :relevant-request="deleteActionState?.relevant_request ?? null"
        @confirm="onDeleteApprovalConfirm"
        @cancel="deleteApprovalNote = ''"
    />
</template>
