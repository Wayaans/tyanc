<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    AtSign,
    CheckCircle2,
    Clock,
    ExternalLink,
    Key,
    Mail,
    Shield,
    ShieldOff,
    Trash2,
    UserPen,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalHistoryPanel from '@/components/cumpu/approvals/ApprovalHistoryPanel.vue';
import ApprovalReasonDialog from '@/components/cumpu/approvals/ApprovalReasonDialog.vue';
import ApprovalRequestBanner from '@/components/cumpu/approvals/ApprovalRequestBanner.vue';
import UserStatusBadge from '@/components/tyanc/users/UserStatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { destroy, edit, index, suspend } from '@/routes/tyanc/users';
import type { UserFormData } from '@/types';
import type { ApprovalContext, GovernedActionState } from '@/types/cumpu';

type Abilities = {
    update: boolean;
    suspend: boolean;
    delete: boolean;
};

const props = defineProps<{
    user: UserFormData;
    abilities: Abilities;
    approvalContext?: ApprovalContext | null;
    status?: string | null;
}>();

const { __, locale } = useTranslations();
const { usersShowBreadcrumbs } = useAppNavigation();

const breadcrumbs = usersShowBreadcrumbs(props.user.name, props.user.id);

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return dateFormatter.value.format(new Date(date));
}

const suspendProcessing = ref(false);
const deleteProcessing = ref(false);
const confirmingDelete = ref(false);
const deleteErrors = ref<Partial<Record<string, string>>>({});

// ── Approval dialog state for delete ─────────────────────────────────────────

const deleteApprovalDialogOpen = ref(false);
const deleteApprovalNote = ref('');

/** The delete governed-action state from the approval context, if present. */
const deleteActionState = computed<GovernedActionState | undefined>(
    () => props.approvalContext?.governed_actions?.['delete'],
);

/**
 * Returns true when the delete must go through the approval-request branch.
 * A blocking request may still short-circuit this into an inline explainer.
 */
const deleteNeedsApprovalDialog = computed<boolean>(() => {
    const s = deleteActionState.value;
    if (!s) return false;
    return s.approval_enabled && !s.bypasses_for_actor && !s.has_usable_grant;
});

/**
 * When there is already a blocking approval request for the delete action,
 * we surface this info rather than opening a new request flow.
 */
const deleteBlockedByRequest = computed(() =>
    deleteActionState.value?.has_blocking_request
        ? deleteActionState.value.relevant_request
        : null,
);

/** Shown inline when the user tries to delete while blocked. */
const deleteBlockedVisible = ref(false);

watch(deleteApprovalDialogOpen, (isOpen) => {
    if (!isOpen) {
        deleteApprovalNote.value = '';
        deleteErrors.value = { ...deleteErrors.value, request_note: undefined };
    }
});

// ── Navigation helpers ────────────────────────────────────────────────────────

function goBack() {
    router.visit(index.url());
}

function goToEdit() {
    router.visit(edit.url({ user: props.user.id }));
}

// ── Suspend ───────────────────────────────────────────────────────────────────

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

// ── Delete flow ───────────────────────────────────────────────────────────────

function handleDelete() {
    // First click: enter confirmation mode.
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;
        deleteBlockedVisible.value = false;

        return;
    }

    deleteBlockedVisible.value = false;

    // Open approval dialog when approval is required and no usable grant.
    if (deleteNeedsApprovalDialog.value) {
        if (deleteBlockedByRequest.value) {
            deleteBlockedVisible.value = true;

            return;
        }

        deleteApprovalDialogOpen.value = true;

        return;
    }

    // Has a usable grant, approval not required, or bypassed — delete directly.
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

            // Re-open the approval dialog so the user can fix the note.
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
    <Head :title="props.user.name" />

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
                    {{ __('All users') }}
                </Button>
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

            <!-- Hero card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <div class="p-6 md:p-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                        <!-- Avatar -->
                        <Avatar class="size-20 shrink-0 text-xl">
                            <AvatarImage
                                :src="props.user.avatar ?? ''"
                                :alt="props.user.name"
                            />
                            <AvatarFallback class="text-xl">
                                {{ getInitials(props.user.name) }}
                            </AvatarFallback>
                        </Avatar>

                        <!-- Identity -->
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="space-y-1">
                                <h1
                                    class="text-2xl font-bold tracking-tight text-foreground"
                                >
                                    {{ props.user.name }}
                                </h1>
                                <div
                                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                                >
                                    <span class="flex items-center gap-1">
                                        <Mail class="size-3.5 shrink-0" />
                                        {{ props.user.email }}
                                    </span>
                                    <span
                                        v-if="props.user.username"
                                        class="flex items-center gap-1 font-mono"
                                    >
                                        <AtSign class="size-3.5 shrink-0" />
                                        {{ props.user.username }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <UserStatusBadge :status="props.user.status" />
                                <Badge
                                    v-for="role in props.user.roles"
                                    :key="role"
                                    variant="secondary"
                                    class="rounded-full"
                                >
                                    {{ role }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Quick actions -->
                        <div
                            class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end"
                        >
                            <Button
                                v-if="props.abilities.update"
                                size="sm"
                                class="gap-2"
                                @click="goToEdit"
                            >
                                <UserPen class="size-4" />
                                {{ __('Edit user') }}
                            </Button>

                            <Button
                                v-if="
                                    props.abilities.suspend &&
                                    props.user.status !== 'suspended'
                                "
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                :disabled="suspendProcessing"
                                @click="suspendUser"
                            >
                                <Spinner v-if="suspendProcessing" />
                                <ShieldOff v-else class="size-4" />
                                {{ __('Suspend') }}
                            </Button>

                            <Button
                                v-if="props.abilities.delete"
                                variant="outline"
                                size="sm"
                                :class="[
                                    'gap-2',
                                    confirmingDelete
                                        ? 'border-destructive text-destructive hover:bg-destructive/10'
                                        : 'text-destructive/70 hover:border-destructive hover:text-destructive',
                                ]"
                                :disabled="deleteProcessing"
                                @click="handleDelete"
                            >
                                <Spinner v-if="deleteProcessing" />
                                <Trash2 v-else class="size-4" />
                                {{
                                    confirmingDelete
                                        ? __('Confirm deletion')
                                        : __('Delete')
                                }}
                            </Button>

                            <Button
                                v-if="confirmingDelete"
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
                    </div>

                    <!-- Blocked delete callout (shown after second click when blocked) -->
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

            <!-- Approval history -->
            <ApprovalHistoryPanel
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />

            <!-- Detail cards grid -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Account card -->
                <div
                    class="rounded-2xl border border-sidebar-border/70 bg-background/90 p-6"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <Shield class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ __('Account') }}
                        </h2>
                    </div>
                    <dl class="space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Username') }}
                            </dt>
                            <dd class="truncate text-right font-mono text-sm">
                                {{
                                    props.user.username
                                        ? `@${props.user.username}`
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Email verified') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{
                                    props.user.email_verified_at
                                        ? formatDate(
                                              props.user.email_verified_at,
                                          )
                                        : __('Not verified')
                                }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Locale') }}
                            </dt>
                            <dd class="text-right text-sm uppercase">
                                {{ props.user.locale }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Timezone') }}
                            </dt>
                            <dd class="truncate text-right text-sm">
                                {{ props.user.timezone }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt
                                class="flex shrink-0 items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Clock class="size-3.5" />
                                {{ __('Last login') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{ formatDate(props.user.last_login_at) }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Last login IP') }}
                            </dt>
                            <dd class="text-right font-mono text-sm">
                                {{ props.user.last_login_ip ?? '—' }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Member since') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{ formatDate(props.user.created_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Roles & Permissions card -->
                <div
                    class="rounded-2xl border border-sidebar-border/70 bg-background/90 p-6"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <Key class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ __('Roles & permissions') }}
                        </h2>
                    </div>

                    <div class="space-y-5">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Roles') }}
                            </p>
                            <div
                                v-if="props.user.roles.length > 0"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="role in props.user.roles"
                                    :key="role"
                                    variant="secondary"
                                    class="rounded-full"
                                >
                                    {{ role }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ __('No roles assigned.') }}
                            </p>
                        </div>

                        <Separator />

                        <div class="space-y-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Direct permissions') }}
                            </p>
                            <div
                                v-if="props.user.permissions.length > 0"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="permission in props.user.permissions"
                                    :key="permission"
                                    variant="outline"
                                    class="rounded-full font-mono text-xs"
                                >
                                    {{ permission }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ __('No direct permissions.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Approval reason dialog for delete submissions -->
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
