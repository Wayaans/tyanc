<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertCircle, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store } from '@/routes/tyanc/files';

const props = defineProps<{
    compact?: boolean;
}>();

const emit = defineEmits<{
    uploaded: [];
}>();

const { __ } = useTranslations();

type UploadProgressEntry = {
    id: string;
    name: string;
    done: boolean;
    failed: boolean;
};

const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref<UploadProgressEntry[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);

const hasUploadErrors = computed(() =>
    uploadProgress.value.some((entry) => entry.failed),
);

function onDragOver(event: DragEvent) {
    event.preventDefault();

    if (isUploading.value) {
        return;
    }

    isDragging.value = true;
}

function onDragLeave() {
    if (isUploading.value) {
        return;
    }

    isDragging.value = false;
}

function onDrop(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;

    if (isUploading.value) {
        return;
    }

    const files = event.dataTransfer?.files;

    if (files && files.length > 0) {
        uploadFiles(Array.from(files));
    }
}

function onInputChange(event: Event) {
    if (isUploading.value) {
        return;
    }

    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (files && files.length > 0) {
        uploadFiles(Array.from(files));
    }

    input.value = '';
}

function uploadFiles(files: File[]) {
    isUploading.value = true;

    const pending = files.map((file, index) => ({
        id: `${file.name}-${file.size}-${index}`,
        file,
    }));

    uploadProgress.value = pending.map(({ id, file }) => ({
        id,
        name: file.name,
        done: false,
        failed: false,
    }));

    function uploadNext() {
        const current = pending.shift();

        if (!current) {
            isUploading.value = false;
            emit('uploaded');

            return;
        }

        const formData = new FormData();

        formData.append('files[]', current.file);

        router.post(store.url(), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                const entry = uploadProgress.value.find(
                    (progress) => progress.id === current.id,
                );

                if (entry) {
                    entry.done = true;
                    entry.failed = false;
                }

                uploadNext();
            },
            onError: () => {
                const entry = uploadProgress.value.find(
                    (progress) => progress.id === current.id,
                );

                if (entry) {
                    entry.done = false;
                    entry.failed = true;
                }

                uploadNext();
            },
        });
    }

    uploadNext();
}

function openFilePicker() {
    if (isUploading.value) {
        return;
    }

    fileInputRef.value?.click();
}

function dismissProgress() {
    if (!isUploading.value) {
        uploadProgress.value = [];
    }
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="props.compact"
            role="button"
            :tabindex="isUploading ? -1 : 0"
            :class="[
                'relative flex w-full max-w-full items-center gap-3 rounded-xl border-2 border-dashed px-4 py-3 transition-colors select-none sm:w-64',
                isUploading
                    ? 'cursor-not-allowed opacity-70'
                    : 'cursor-pointer',
                isDragging
                    ? 'border-primary/50 bg-primary/5'
                    : 'border-sidebar-border/70 bg-sidebar/20 hover:border-sidebar-border hover:bg-sidebar/40',
            ]"
            :aria-disabled="isUploading"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="openFilePicker"
            @keydown.enter.space.prevent="openFilePicker"
        >
            <input
                ref="fileInputRef"
                type="file"
                multiple
                class="sr-only"
                :disabled="isUploading"
                @change="onInputChange"
            />

            <div
                :class="[
                    'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                    isDragging
                        ? 'bg-primary/10 text-primary'
                        : 'bg-muted text-muted-foreground',
                ]"
            >
                <Upload class="size-4" />
            </div>

            <div class="min-w-0">
                <p class="truncate text-xs font-medium text-foreground">
                    {{
                        isDragging
                            ? __('Drop files here')
                            : __('Drop files or browse')
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ __('Tyanc shared library') }}
                </p>
            </div>
        </div>

        <div
            v-else
            role="button"
            :tabindex="isUploading ? -1 : 0"
            :class="[
                'relative flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed p-8 transition-colors select-none',
                isUploading
                    ? 'cursor-not-allowed opacity-70'
                    : 'cursor-pointer',
                isDragging
                    ? 'border-primary/50 bg-primary/5'
                    : 'border-sidebar-border/70 bg-sidebar/20 hover:border-sidebar-border hover:bg-sidebar/40',
            ]"
            :aria-disabled="isUploading"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="openFilePicker"
            @keydown.enter.space.prevent="openFilePicker"
        >
            <input
                ref="fileInputRef"
                type="file"
                multiple
                class="sr-only"
                :disabled="isUploading"
                @change="onInputChange"
            />

            <div
                :class="[
                    'flex size-12 items-center justify-center rounded-xl transition-colors',
                    isDragging
                        ? 'bg-primary/10 text-primary'
                        : 'bg-muted text-muted-foreground',
                ]"
            >
                <Upload class="size-6" />
            </div>

            <div class="text-center">
                <p class="text-sm font-medium text-foreground">
                    {{
                        isDragging
                            ? __('Drop files here to upload')
                            : __(
                                  'Drag and drop files here, or browse from your device.',
                              )
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        __(
                            'Supports images, documents, spreadsheets, and more up to 10 MB each.',
                        )
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="uploadProgress.length > 0"
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background/90"
        >
            <div
                class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-2"
            >
                <span class="text-xs font-medium text-muted-foreground">
                    {{
                        isUploading
                            ? __('Uploading…')
                            : hasUploadErrors
                              ? __('Upload finished with errors')
                              : __('Upload complete')
                    }}
                </span>

                <Button
                    v-if="!isUploading"
                    variant="ghost"
                    size="icon"
                    class="size-6"
                    :aria-label="__('Dismiss')"
                    @click="dismissProgress"
                >
                    <X class="size-3" />
                </Button>
            </div>

            <ul class="divide-y divide-sidebar-border/50">
                <li
                    v-for="entry in uploadProgress"
                    :key="entry.id"
                    class="flex items-center gap-3 px-4 py-2"
                >
                    <Spinner
                        v-if="!entry.done && !entry.failed && isUploading"
                        class="size-3.5 shrink-0 text-primary"
                    />
                    <AlertCircle
                        v-else-if="entry.failed"
                        class="size-3.5 shrink-0 text-destructive"
                    />
                    <span
                        v-else
                        :class="[
                            'size-3.5 shrink-0 rounded-full',
                            entry.done
                                ? 'bg-emerald-500/80'
                                : 'bg-muted-foreground/30',
                        ]"
                    />
                    <span
                        :class="[
                            'truncate text-xs',
                            entry.failed
                                ? 'text-destructive'
                                : entry.done
                                  ? 'text-muted-foreground'
                                  : 'text-foreground',
                        ]"
                    >
                        {{ entry.name }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
