<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, ExternalLink } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalReasonDialog from '@/components/cumpu/approvals/ApprovalReasonDialog.vue';
import InputError from '@/components/InputError.vue';
import AppearancePreview from '@/components/tyanc/settings/AppearancePreview.vue';
import SettingsFormFooter from '@/components/tyanc/settings/SettingsFormFooter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslations } from '@/lib/translations';
import { update } from '@/routes/tyanc/settings/appearance';
import type { ApprovalContext, GovernedActionState } from '@/types/cumpu';

type Option = { value: string; label: string };
type FontFamily = { value: string; label: string; stack: string };
type SpacingDensity = { value: string; label: string; density: number };

type Settings = {
    primary_color: string;
    secondary_color: string;
    border_radius: string;
    spacing_density: string;
    spacing_density_value: number;
    font_family: string;
    font_family_stack: string;
    sidebar_variant: string;
};

const props = defineProps<{
    settings: Settings;
    fontFamilies: FontFamily[];
    sidebarVariants: Option[];
    spacingDensities: SpacingDensity[];
    approvalContext?: ApprovalContext | null;
}>();

const isOpen = ref(false);

const BORDER_RADIUS_OPTIONS = [
    { value: '0rem', label: 'None' },
    { value: '0.125rem', label: 'XS — 2px' },
    { value: '0.25rem', label: 'SM — 4px' },
    { value: '0.375rem', label: 'MD — 6px' },
    { value: '0.5rem', label: 'LG — 8px' },
    { value: '0.75rem', label: 'XL — 12px' },
    { value: '1rem', label: '2XL — 16px' },
];

const primaryColor = ref(props.settings.primary_color);
const secondaryColor = ref(props.settings.secondary_color);
const borderRadius = ref(props.settings.border_radius);
const spacingDensity = ref(props.settings.spacing_density);
const fontFamily = ref(props.settings.font_family);
const sidebarVariant = ref(props.settings.sidebar_variant);

const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

const borderRadiusOptions = computed(() => {
    const found = BORDER_RADIUS_OPTIONS.find(
        (o) => o.value === borderRadius.value,
    );
    if (found) {
        return BORDER_RADIUS_OPTIONS;
    }
    return [
        { value: borderRadius.value, label: borderRadius.value },
        ...BORDER_RADIUS_OPTIONS,
    ];
});

const previewFontStack = computed(
    () =>
        props.fontFamilies.find((f) => f.value === fontFamily.value)?.stack ??
        props.settings.font_family_stack,
);

const previewFontLabel = computed(
    () =>
        props.fontFamilies.find((f) => f.value === fontFamily.value)?.label ??
        fontFamily.value,
);

const previewSpacingLabel = computed(
    () =>
        props.spacingDensities.find((d) => d.value === spacingDensity.value)
            ?.label ?? spacingDensity.value,
);

const previewSidebarLabel = computed(
    () =>
        props.sidebarVariants.find((v) => v.value === sidebarVariant.value)
            ?.label ?? sidebarVariant.value,
);

watch(
    () => props.settings,
    (settings) => {
        primaryColor.value = settings.primary_color;
        secondaryColor.value = settings.secondary_color;
        borderRadius.value = settings.border_radius;
        spacingDensity.value = settings.spacing_density;
        fontFamily.value = settings.font_family;
        sidebarVariant.value = settings.sidebar_variant;
    },
);

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
            primary_color: primaryColor.value,
            secondary_color: secondaryColor.value,
            border_radius: borderRadius.value,
            spacing_density: spacingDensity.value,
            font_family: fontFamily.value,
            sidebar_variant: sidebarVariant.value,
            request_note: note || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (updateNeedsApprovalDialog.value) {
                    recentlySuccessful.value = false;
                    isOpen.value = false;
                    return;
                }

                recentlySuccessful.value = true;
                setTimeout(() => {
                    recentlySuccessful.value = false;
                    isOpen.value = false;
                }, 1500);
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

const { __ } = useTranslations();
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button variant="outline" size="sm">{{
                __('Edit appearance')
            }}</Button>
        </SheetTrigger>

        <SheetContent
            side="right"
            class="flex flex-col overflow-y-auto sm:max-w-md"
        >
            <SheetHeader class="px-6 pt-6">
                <SheetTitle>{{ __('Edit appearance') }}</SheetTitle>
                <SheetDescription>
                    {{
                        __(
                            'Changes apply globally. Users can override via personal preferences.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <!-- Live preview -->
            <div class="px-6">
                <AppearancePreview
                    :primary-color="primaryColor"
                    :secondary-color="secondaryColor"
                    :border-radius="borderRadius"
                    :font-family-stack="previewFontStack"
                    :font-family-label="previewFontLabel"
                    :spacing-density-label="previewSpacingLabel"
                    :sidebar-variant-label="previewSidebarLabel"
                />
            </div>

            <form
                class="flex-1 space-y-5 overflow-y-auto px-6 pb-6"
                @submit.prevent="handleSubmit"
            >
                <!-- Colors -->
                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium">
                        {{ __('Colors') }}
                    </legend>
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid gap-2">
                            <Label for="primary_color">{{
                                __('Primary')
                            }}</Label>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex size-9 shrink-0 rounded-md border"
                                    :style="{ background: primaryColor }"
                                />
                                <Input
                                    id="primary_color"
                                    v-model="primaryColor"
                                    type="text"
                                    placeholder="oklch(0.5 0.17 200) or #0f766e"
                                    class="font-mono"
                                />
                            </div>
                            <InputError :message="errors.primary_color" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="secondary_color">{{
                                __('Secondary')
                            }}</Label>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex size-9 shrink-0 rounded-md border"
                                    :style="{ background: secondaryColor }"
                                />
                                <Input
                                    id="secondary_color"
                                    v-model="secondaryColor"
                                    type="text"
                                    placeholder="oklch(0.96 0 0) or #f5f5f5"
                                    class="font-mono"
                                />
                            </div>
                            <InputError :message="errors.secondary_color" />
                        </div>
                    </div>
                </fieldset>

                <!-- Border radius -->
                <div class="grid gap-2">
                    <Label for="border_radius">{{ __('Border radius') }}</Label>
                    <Select v-model="borderRadius">
                        <SelectTrigger id="border_radius" class="w-full">
                            <SelectValue :placeholder="__('Select radius')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opt in borderRadiusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.border_radius" />
                </div>

                <!-- Font family -->
                <div class="grid gap-2">
                    <Label for="font_family">{{ __('Font family') }}</Label>
                    <Select v-model="fontFamily">
                        <SelectTrigger id="font_family" class="w-full">
                            <SelectValue :placeholder="__('Select font')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="font in props.fontFamilies"
                                :key="font.value"
                                :value="font.value"
                            >
                                {{ font.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.font_family" />
                </div>

                <!-- Spacing density -->
                <div class="grid gap-2">
                    <Label for="spacing_density">{{
                        __('Spacing density')
                    }}</Label>
                    <Select v-model="spacingDensity">
                        <SelectTrigger id="spacing_density" class="w-full">
                            <SelectValue :placeholder="__('Select density')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="density in props.spacingDensities"
                                :key="density.value"
                                :value="density.value"
                            >
                                {{ density.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.spacing_density" />
                </div>

                <!-- Sidebar variant -->
                <div class="grid gap-2">
                    <Label for="sidebar_variant">{{
                        __('Sidebar style')
                    }}</Label>
                    <Select v-model="sidebarVariant">
                        <SelectTrigger id="sidebar_variant" class="w-full">
                            <SelectValue :placeholder="__('Select style')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="variant in props.sidebarVariants"
                                :key="variant.value"
                                :value="variant.value"
                            >
                                {{ variant.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.sidebar_variant" />
                </div>

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
        </SheetContent>
    </Sheet>

    <ApprovalReasonDialog
        v-model:open="approvalDialogOpen"
        v-model:note="approvalNote"
        :title="__('Save appearance settings')"
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
