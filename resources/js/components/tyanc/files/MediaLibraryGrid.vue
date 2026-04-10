<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FileX, MoreHorizontal, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/translations';
import { destroy } from '@/routes/tyanc/files';
import type { MediaFileRow } from '@/types';

const props = defineProps<{
    files: MediaFileRow[];
}>();

const emit = defineEmits<{
    preview: [file: MediaFileRow];
}>();

const { __ } = useTranslations();

const confirmingDeleteId = ref<number | null>(null);

function mimeTypeShort(mime: string | null): string {
    if (!mime) {
        return '?';
    }

    return (mime.split('/')[1] ?? mime).toUpperCase();
}

function handleDelete(fileId: number) {
    if (confirmingDeleteId.value !== fileId) {
        confirmingDeleteId.value = fileId;

        return;
    }

    router.delete(destroy.url({ media: fileId }), {
        preserveScroll: true,
        onFinish: () => {
            confirmingDeleteId.value = null;
        },
    });
}

function onMenuClose(open: boolean, fileId: number) {
    if (!open && confirmingDeleteId.value === fileId) {
        confirmingDeleteId.value = null;
    }
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
            {{ __('Upload your first file to start the shared library.') }}
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
                        v-if="file.preview_url"
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
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ file.size_human }}
                    </p>
                </div>

                <DropdownMenu
                    @update:open="(open) => onMenuClose(open, file.id)"
                >
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-6 shrink-0 opacity-0 transition-opacity group-hover:opacity-100 data-[state=open]:opacity-100"
                            :aria-label="__('File actions')"
                        >
                            <MoreHorizontal class="size-3.5" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem
                            :class="[
                                'gap-2 focus:bg-destructive/10',
                                confirmingDeleteId === file.id
                                    ? 'text-destructive focus:text-destructive'
                                    : 'text-destructive/80 focus:text-destructive',
                            ]"
                            @click.stop="handleDelete(file.id)"
                        >
                            <Trash2 class="size-3.5 shrink-0" />
                            <span class="truncate text-xs">
                                {{
                                    confirmingDeleteId === file.id
                                        ? __('Click again to confirm deletion')
                                        : __('Delete file')
                                }}
                            </span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Type badge -->
            <div class="absolute top-2 left-2">
                <Badge
                    variant="outline"
                    class="rounded-full bg-background/80 font-mono text-xs backdrop-blur-sm"
                >
                    {{ mimeTypeShort(file.mime_type) }}
                </Badge>
            </div>
        </div>
    </div>
</template>
