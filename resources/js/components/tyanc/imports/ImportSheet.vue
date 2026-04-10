<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Loader2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';
import { store } from '@/routes/tyanc/users/import';
import type { ImportRunRow, ImportStatus } from '@/types';

const props = withDefaults(
    defineProps<{
        recentImports: ImportRunRow[];
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const { __ } = useTranslations();

const open = ref(false);
const isUploading = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const requestNote = ref('');

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

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

function submitImport() {
    if (!selectedFile.value) {
        return;
    }

    isUploading.value = true;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    if (requestNote.value.trim() !== '') {
        formData.append('request_note', requestNote.value.trim());
    }

    router.post(store.url(), formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            isUploading.value = false;
            selectedFile.value = null;
            requestNote.value = '';
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
            open.value = false;
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
                    {{
                        __(
                            'Upload a spreadsheet to create or update managed users after approval.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex flex-col gap-5 overflow-y-auto p-5">
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
                        class="flex w-full flex-col items-center gap-3 rounded-xl border-2 border-dashed border-sidebar-border/70 p-6 text-center transition-colors hover:border-sidebar-border hover:bg-sidebar/30"
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
                                        : __('Import file')
                                }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{
                                    __(
                                        'Supports images, documents, spreadsheets, and more up to 10 MB each.',
                                    )
                                }}
                            </p>
                        </div>
                    </button>
                </div>

                <div class="space-y-3">
                    <Label for="users-import-note">{{
                        __('Request note')
                    }}</Label>
                    <Textarea
                        id="users-import-note"
                        v-model="requestNote"
                        :placeholder="__('Request note')"
                        rows="3"
                    />
                </div>

                <Button
                    class="gap-2"
                    :disabled="!selectedFile || isUploading"
                    @click="submitImport"
                >
                    <Loader2 v-if="isUploading" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    {{
                        isUploading
                            ? __('Saving…')
                            : __('Submit import request')
                    }}
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
</template>
