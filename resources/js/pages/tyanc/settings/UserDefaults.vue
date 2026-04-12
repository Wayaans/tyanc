<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, Clock, ExternalLink } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalHistoryPanel from '@/components/cumpu/approvals/ApprovalHistoryPanel.vue';
import ApprovalReasonDialog from '@/components/cumpu/approvals/ApprovalReasonDialog.vue';
import ApprovalRequestBanner from '@/components/cumpu/approvals/ApprovalRequestBanner.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import SettingsFormFooter from '@/components/tyanc/settings/SettingsFormFooter.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import TyancSettingsLayout from '@/layouts/tyanc/settings/Layout.vue';
import { useTranslations } from '@/lib/translations';
import { edit, update } from '@/routes/tyanc/settings/user-defaults';
import type { ApprovalContext, GovernedActionState } from '@/types/cumpu';

type Option = { value: string; label: string };

type Settings = {
    locale: string;
    timezone: string;
    appearance: string;
};

type Props = {
    settings: Settings;
    appearances: Option[];
    locales: string[];
    timezones: string[];
    approvalContext?: ApprovalContext | null;
    status?: string | null;
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
    tyancSettingsBreadcrumbs('Defaults for New Users', edit()),
);
const { __ } = useTranslations();

const selectedLocale = ref(props.settings.locale);
const selectedTimezone = ref(props.settings.timezone);
const selectedAppearance = ref(props.settings.appearance);
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

// ── Approval dialog state ─────────────────────────────────────────────────────

const approvalDialogOpen = ref(false);
const approvalNote = ref('');

const updateActionState = computed<GovernedActionState | undefined>(
    () => props.approvalContext?.governed_actions?.['update'],
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

    doSubmit('');
}

function onApprovalConfirm() {
    approvalDialogOpen.value = false;
    doSubmit(approvalNote.value);
}

function doSubmit(note: string) {
    processing.value = true;
    errors.value = {};
    recentlySuccessful.value = false;

    router.patch(
        update.url(),
        {
            locale: selectedLocale.value,
            timezone: selectedTimezone.value,
            appearance: selectedAppearance.value,
            request_note: note || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (updateNeedsApprovalDialog.value) {
                    recentlySuccessful.value = false;
                    return;
                }

                recentlySuccessful.value = true;
                setTimeout(() => {
                    recentlySuccessful.value = false;
                }, 2000);
            },
            onError: (responseErrors) => {
                errors.value = responseErrors as Partial<
                    Record<string, string>
                >;
                if (responseErrors.request_note || responseErrors.approval) {
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="__('Defaults for New Users')" />

        <h1 class="sr-only">{{ __('Defaults for New Users') }}</h1>

        <TyancSettingsLayout>
            <!-- Status feedback -->
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

            <ApprovalRequestBanner
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />

            <form class="space-y-6" @submit.prevent="handleSubmit">
                <!-- Locale & time -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="__('Locale & time')"
                        :description="
                            __(
                                'Starting values applied when new user accounts are created',
                            )
                        "
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="locale">{{
                                __('Default language')
                            }}</Label>
                            <Select v-model="selectedLocale">
                                <SelectTrigger id="locale" class="w-full">
                                    <SelectValue
                                        :placeholder="__('Select language')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="locale in props.locales"
                                        :key="locale"
                                        :value="locale"
                                    >
                                        {{ locale.toUpperCase() }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.locale" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="timezone">{{
                                __('Default timezone')
                            }}</Label>
                            <TimezoneCombobox
                                id="timezone"
                                v-model="selectedTimezone"
                                :timezones="props.timezones"
                            />
                            <InputError :message="errors.timezone" />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Appearance -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="__('Appearance')"
                        :description="
                            __(
                                'Starting theme preference for newly created user accounts',
                            )
                        "
                    />

                    <div class="grid max-w-xs gap-2">
                        <Label for="appearance">{{
                            __('Default theme')
                        }}</Label>
                        <Select v-model="selectedAppearance">
                            <SelectTrigger id="appearance" class="w-full">
                                <SelectValue
                                    :placeholder="__('Select theme')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in props.appearances"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.appearance" />
                    </div>
                </div>

                <Separator />

                <!-- Blocked submission callout -->
                <div
                    v-if="submissionBlockedVisible && updateBlockedByRequest"
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

                <SettingsFormFooter
                    :processing="processing"
                    :recently-successful="recentlySuccessful"
                />
            </form>

            <ApprovalHistoryPanel
                v-if="props.approvalContext"
                :context="props.approvalContext"
            />
        </TyancSettingsLayout>
    </AppLayout>

    <ApprovalReasonDialog
        v-model:open="approvalDialogOpen"
        v-model:note="approvalNote"
        :title="__('Save user defaults')"
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
