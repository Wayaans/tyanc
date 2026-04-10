<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store } from '@/routes/tyanc/files';

const emit = defineEmits<{
    uploaded: [];
}>();

const { __ } = useTranslations();

const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref<{ name: string; done: boolean }[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);

function onDragOver(event: DragEvent) {
    event.preventDefault();
    isDragging.value = true;
}

function onDragLeave() {
    isDragging.value = false;
}

function onDrop(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;

    const files = event.dataTransfer?.files;

    if (files && files.length > 0) {
        uploadFiles(Array.from(files));
    }
}

function onInputChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (files && files.length > 0) {
        uploadFiles(Array.from(files));
    }

    input.value = '';
}

function uploadFiles(files: File[]) {
    isUploading.value = true;
    uploadProgress.value = files.map((f) => ({ name: f.name, done: false }));

    const pending = [...files];

    function uploadNext() {
        const file = pending.shift();

        if (!file) {
            isUploading.value = false;
            uploadProgress.value = [];
            emit('uploaded');

            return;
        }

        const formData = new FormData();

        formData.append('files[]', file);

        router.post(store.url(), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                const entry = uploadProgress.value.find(
                    (p) => p.name === file.name,
                );

                if (entry) {
                    entry.done = true;
                }

                uploadNext();
            },
            onError: () => {
                uploadNext();
            },
        });
    }

    uploadNext();
}

function openFilePicker() {
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
        <!-- Dropzone area -->
        <div
            role="button"
            tabindex="0"
            :class="[
                'relative flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed p-8 transition-colors select-none',
                isDragging
                    ? 'border-primary/50 bg-primary/5'
                    : 'border-sidebar-border/70 bg-sidebar/20 hover:border-sidebar-border hover:bg-sidebar/40',
            ]"
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

        <!-- Upload progress -->
        <div
            v-if="uploadProgress.length > 0"
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background/90"
        >
            <div
                class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-2"
            >
                <span class="text-xs font-medium text-muted-foreground">
                    {{ isUploading ? __('Uploading…') : __('Upload complete') }}
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
                    :key="entry.name"
                    class="flex items-center gap-3 px-4 py-2"
                >
                    <Spinner
                        v-if="!entry.done && isUploading"
                        class="size-3.5 shrink-0 text-primary"
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
                            entry.done
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
