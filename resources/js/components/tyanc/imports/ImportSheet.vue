<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    ExternalLink,
    Info,
    Loader2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ApprovalReasonDialog from '@/components/cumpu/approvals/ApprovalReasonDialog.vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslations } from '@/lib/translations';
import { store } from '@/routes/tyanc/users/import';
import type { ImportRunRow, ImportStatus } from '@/types';
import type { GovernedActionState } from '@/types/cumpu';

const props = withDefaults(
    defineProps<{
        recentImports: ImportRunRow[];
        disabled?: boolean;
        approvalState?: GovernedActionState | null;
    }>(),
    {
        disabled: false,
        approvalState: null,
    },
);

const { __ } = useTranslations();

const open = ref(false);
const isUploading = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const approvalDialogOpen = ref(false);
const approvalNote = ref('');
const errors = ref<Partial<Record<string, string>>>({});

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const requiresApproval = computed<boolean>(() => {
    const state = props.approvalState;

    if (!state) {
        return false;
    }

    return (
        state.approval_enabled &&
        state.approval_required &&
        !state.has_usable_grant
    );
});

const hasUsableGrant = computed<boolean>(() =>
    Boolean(props.approvalState?.has_usable_grant),
);
const isBlocked = computed<boolean>(() =>
    Boolean(props.approvalState?.has_blocking_request),
);
const blockingRequest = computed(() =>
    isBlocked.value ? (props.approvalState?.relevant_request ?? null) : null,
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

const statusConfig: Record<
    ImportStatus,
    { label: string; class: string; icon: typeof Clock }
> = {
    pending_approval: {
        label: 'Pending approval',
        class: 'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
        icon: Clock,
    },
    queued: {
        label: 'Queued',
        class: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        icon: Clock,
    },
    processing: {
        label: 'Processing',
        class: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        icon: Loader2,
    },
    completed: {
        label: 'Completed',
        class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        icon: CheckCircle2,
    },
    failed: {
        label: 'Failed',
        class: 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
        icon: AlertCircle,
    },
};

function selectFile(event: Event) {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
}

function openFilePicker() {
    fileInputRef.value?.click();
}

function handleSubmit() {
    if (!selectedFile.value || isBlocked.value) {
        return;
    }

    if (requiresApproval.value) {
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
    if (!selectedFile.value || isBlocked.value) {
        return;
    }

    isUploading.value = true;
    errors.value = {};

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    if (note.trim() !== '') {
        formData.append('request_note', note.trim());
    }

    router.post(store.url(), formData, {
        forceFormData: true,
        preserveScroll: true,
        onError: (responseErrors) => {
            errors.value = responseErrors as Partial<Record<string, string>>;

            if (responseErrors.request_note || responseErrors.approval) {
                approvalNote.value = note;
                approvalDialogOpen.value = true;
            }
        },
        onFinish: () => {
            isUploading.value = false;

            if (
                !errors.value.file &&
                !errors.value.request_note &&
                !errors.value.approval
            ) {
                selectedFile.value = null;
                approvalNote.value = '';

                if (fileInputRef.value) {
                    fileInputRef.value.value = '';
                }

                open.value = false;
            }
        },
    });
}
</script>

<template>
    <Button
        v-if="props.disabled"
        variant="outline"
        size="sm"
        class="gap-2 opacity-60"
        disabled
    >
        <Upload class="size-4" />
        {{ __('Import') }}
    </Button>

    <Sheet v-else v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2">
                <Upload class="size-4" />
                {{ __('Import') }}
            </Button>
        </SheetTrigger>

        <SheetContent class="flex w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-b border-sidebar-border/70 p-5">
                <SheetTitle>{{ __('Import users') }}</SheetTitle>
                <SheetDescription>
                    <template v-if="requiresApproval">
                        {{
                            __(
                                'Upload a spreadsheet to request a user import. After approval, upload the file again to process it.',
                            )
                        }}
                    </template>
                    <template v-else-if="hasUsableGrant">
                        {{
                            __(
                                'Your approval is ready. Upload the spreadsheet now to process this import once.',
                            )
                        }}
                    </template>
                    <template v-else>
                        {{
                            __(
                                'Upload a spreadsheet to create or update managed users.',
                            )
                        }}
                    </template>
                </SheetDescription>
            </SheetHeader>

            <div class="flex flex-col gap-5 overflow-y-auto p-5">
                <div
                    v-if="isBlocked && blockingRequest"
                    class="flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/[0.07]"
                >
                    <Clock
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <div class="min-w-0 flex-1 space-y-1">
                        <p
                            class="text-sm font-medium text-amber-900 dark:text-amber-200"
                        >
                            {{ __('An import request is already pending.') }}
                        </p>
                        <p
                            class="text-xs text-amber-700/80 dark:text-amber-300/80"
                        >
                            {{
                                __(
                                    'Once the pending request is approved, come back and upload your file to start the import.',
                                )
                            }}
                        </p>
                    </div>
                    <a
                        v-if="blockingRequest.detail_url"
                        :href="blockingRequest.detail_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex shrink-0 items-center gap-1 rounded-lg border border-amber-200/80 bg-white/60 px-2.5 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100/60 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                    >
                        {{ __('View request') }}
                        <ExternalLink class="size-3" />
                    </a>
                </div>

                <div
                    v-else-if="requiresApproval"
                    class="flex items-start gap-3 rounded-xl border border-sky-200/60 bg-sky-50/50 px-4 py-3 dark:border-sky-500/20 dark:bg-sky-500/[0.07]"
                >
                    <Info
                        class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-400"
                    />
                    <div class="space-y-1">
                        <p
                            class="text-sm font-medium text-sky-900 dark:text-sky-200"
                        >
                            {{ __('Approval required') }}
                        </p>
                        <p class="text-xs text-sky-700/80 dark:text-sky-300/80">
                            {{
                                __(
                                    'Submitting this file will create an approval request. After approval, you must upload the file again to run the import.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="hasUsableGrant"
                    class="flex items-start gap-3 rounded-xl border border-emerald-200/60 bg-emerald-50/50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/[0.07]"
                >
                    <CheckCircle2
                        class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <div class="space-y-1">
                        <p
                            class="text-sm font-medium text-emerald-900 dark:text-emerald-200"
                        >
                            {{ __('Approval ready') }}
                        </p>
                        <p
                            class="text-xs text-emerald-700/80 dark:text-emerald-300/80"
                        >
                            {{
                                __(
                                    'Upload the spreadsheet now to consume the grant and queue the import.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <Label for="users-import-file">{{
                        __('Import file')
                    }}</Label>

                    <input
                        ref="fileInputRef"
                        id="users-import-file"
                        type="file"
                        accept=".csv,.xlsx,.xls"
                        class="sr-only"
                        @change="selectFile"
                    />

                    <button
                        type="button"
                        :disabled="isBlocked"
                        class="flex w-full flex-col items-center gap-3 rounded-xl border-2 border-dashed border-sidebar-border/70 p-6 text-center transition-colors hover:border-sidebar-border hover:bg-sidebar/30 disabled:pointer-events-none disabled:opacity-50"
                        @click="openFilePicker"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <Upload class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                {{
                                    selectedFile
                                        ? selectedFile.name
                                        : __('Click to select a file')
                                }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ __('CSV, XLSX or XLS up to 10 MB') }}
                            </p>
                        </div>
                    </button>
                    <FormFieldSupport :error="errors.file" />
                </div>

                <Button
                    class="gap-2"
                    :disabled="!selectedFile || isUploading || isBlocked"
                    @click="handleSubmit"
                >
                    <Loader2 v-if="isUploading" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    <template v-if="isUploading">
                        {{ __('Saving…') }}
                    </template>
                    <template v-else-if="requiresApproval">
                        {{ __('Submit import request') }}
                    </template>
                    <template v-else>
                        {{ __('Import users') }}
                    </template>
                </Button>

                <Separator />

                <div>
                    <h3 class="mb-3 text-sm font-medium text-foreground">
                        {{ __('Recent imports') }}
                    </h3>

                    <div
                        v-if="props.recentImports.length === 0"
                        class="flex flex-col items-center gap-2 py-6 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ __('No imports yet.') }}
                        </p>
                    </div>

                    <ul
                        v-else
                        class="divide-y divide-sidebar-border/50 overflow-hidden rounded-xl border border-sidebar-border/70"
                    >
                        <li
                            v-for="run in props.recentImports"
                            :key="run.id"
                            class="flex items-start gap-3 bg-background px-4 py-3"
                        >
                            <component
                                :is="statusConfig[run.status].icon"
                                :class="[
                                    'mt-0.5 size-4 shrink-0',
                                    run.status === 'processing'
                                        ? 'animate-spin'
                                        : '',
                                ]"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="truncate text-xs font-medium text-foreground"
                                    >
                                        {{ run.file_name }}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        :class="`rounded-full text-xs ${statusConfig[run.status].class}`"
                                    >
                                        {{ __(statusConfig[run.status].label) }}
                                    </Badge>
                                    <Badge
                                        v-if="run.approval_status"
                                        variant="outline"
                                        class="rounded-full text-xs"
                                    >
                                        {{
                                            __(
                                                run.approval_status ===
                                                    'pending'
                                                    ? 'Pending approval'
                                                    : run.approval_status ===
                                                        'approved'
                                                      ? 'Approval approved'
                                                      : 'Approval rejected',
                                            )
                                        }}
                                    </Badge>
                                </div>

                                <p class="text-xs text-muted-foreground">
                                    {{ __('Processed rows') }}:
                                    {{ run.processed_rows }} ·
                                    {{
                                        dateFormatter.format(
                                            new Date(run.created_at),
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="run.failure_message"
                                    class="text-xs text-red-600 dark:text-red-400"
                                >
                                    {{ run.failure_message }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <ApprovalReasonDialog
        v-model:open="approvalDialogOpen"
        v-model:note="approvalNote"
        :title="__('Submit import request')"
        :description="
            __(
                'This action requires approval. Explain why this import should be approved.',
            )
        "
        :action-label="__('Submit for approval')"
        :loading="isUploading"
        :error="errors.request_note ?? errors.approval"
        :relevant-request="props.approvalState?.relevant_request ?? null"
        @confirm="onApprovalConfirm"
        @cancel="approvalNote = ''"
    />
</template>
