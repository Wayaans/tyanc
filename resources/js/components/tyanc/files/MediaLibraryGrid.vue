<script setup lang="ts">
import { FileX } from 'lucide-vue-next';
import FileActionsDropdown from '@/components/tyanc/files/FileActionsDropdown.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { MediaFileRow } from '@/types';

const props = defineProps<{
    files: MediaFileRow[];
    canDownload: boolean;
    canDelete: boolean;
}>();

const emit = defineEmits<{
    preview: [file: MediaFileRow];
}>();

const { __ } = useTranslations();

function mimeTypeShort(mime: string | null): string {
    if (!mime) {
        return '?';
    }

    return (mime.split('/')[1] ?? mime).toUpperCase();
}

function sourceLabel(source: string): string {
    if (source === 'media_library') {
        return __('Media');
    }

    if (source === 'public_disk') {
        return __('Public');
    }

    return source;
}
</script>

<template>
    <!-- Empty state -->
    <div
        v-if="props.files.length === 0"
        class="flex flex-col items-center justify-center gap-3 py-16 text-center"
    >
        <div
            class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
        >
            <FileX class="size-6" />
        </div>
        <p class="text-sm font-medium text-foreground">
            {{ __('No files found.') }}
        </p>
        <p class="text-xs text-muted-foreground">
            {{ __('No files are available in this view yet.') }}
        </p>
    </div>

    <!-- Grid -->
    <div
        v-else
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
    >
        <div
            v-for="file in props.files"
            :key="file.id"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background/90 transition-shadow hover:shadow-sm"
        >
            <!-- Thumbnail -->
            <button
                class="relative block w-full overflow-hidden"
                :aria-label="`${__('Preview')} ${file.file_name}`"
                @click="emit('preview', file)"
            >
                <div
                    class="flex aspect-square items-center justify-center bg-muted/40"
                >
                    <img
                        v-if="file.is_image && file.preview_url"
                        :src="file.preview_url"
                        :alt="file.file_name"
                        class="size-full object-cover transition-transform group-hover:scale-105"
                        loading="lazy"
                    />
                    <span
                        v-else
                        class="font-mono text-xs font-medium text-muted-foreground uppercase"
                    >
                        {{ mimeTypeShort(file.mime_type) }}
                    </span>
                </div>
            </button>

            <!-- Info bar -->
            <div
                class="flex items-start justify-between gap-1 px-2 pt-1.5 pb-2"
            >
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-xs leading-snug font-medium text-foreground"
                    >
                        {{ file.name }}
                    </p>
                    <div class="mt-0.5 flex flex-wrap items-center gap-1">
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            {{ file.size_human }}
                        </span>
                        <span
                            v-if="file.app_label"
                            class="text-xs text-muted-foreground"
                        >
                            ·
                        </span>
                        <span
                            v-if="file.app_label"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ file.app_label }}
                        </span>
                    </div>
                </div>

                <FileActionsDropdown
                    :file="file"
                    :can-download="props.canDownload"
                    :can-delete="props.canDelete"
                    :on-preview="() => emit('preview', file)"
                    class="opacity-0 transition-opacity group-hover:opacity-100 data-[state=open]:opacity-100"
                />
            </div>

            <!-- Badges -->
            <div class="absolute top-2 left-2 flex flex-col gap-1">
                <Badge
                    variant="outline"
                    class="rounded-full bg-background/80 font-mono text-xs backdrop-blur-sm"
                >
                    {{ mimeTypeShort(file.mime_type) }}
                </Badge>
                <Badge
                    v-if="file.source"
                    variant="secondary"
                    class="rounded-full text-xs backdrop-blur-sm"
                >
                    {{ sourceLabel(file.source) }}
                </Badge>
            </div>

            <!-- Folder label -->
            <div
                v-if="file.folder_label"
                class="absolute right-2 bottom-10 max-w-[60%]"
            >
                <Badge
                    variant="outline"
                    class="truncate rounded-full bg-background/80 text-xs backdrop-blur-sm"
                >
                    {{ file.folder_label }}
                </Badge>
            </div>
        </div>
    </div>
</template>
