<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Download,
    ExternalLink,
    MoreHorizontal,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/translations';
import { destroy } from '@/routes/tyanc/files';
import type { MediaFileRow } from '@/types';

const props = defineProps<{
    file: MediaFileRow;
    canDownload: boolean;
    canDelete: boolean;
    onPreview?: () => void;
}>();

const { __ } = useTranslations();

const confirmingDelete = ref(false);

function handleDelete() {
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;

        return;
    }

    router.delete(destroy.url({ managedFile: props.file.id }), {
        preserveScroll: true,
        onFinish: () => {
            confirmingDelete.value = false;
        },
    });
}

function onMenuClose(open: boolean) {
    if (!open) {
        confirmingDelete.value = false;
    }
}
</script>

<template>
    <DropdownMenu @update:open="onMenuClose">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-7 shrink-0"
                :aria-label="__('File actions')"
            >
                <MoreHorizontal class="size-3.5" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem
                v-if="props.onPreview"
                class="gap-2 text-xs"
                @click="props.onPreview?.()"
            >
                {{ __('Preview') }}
            </DropdownMenuItem>

            <DropdownMenuItem as-child class="gap-2 text-xs">
                <a
                    :href="props.file.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2"
                >
                    <ExternalLink class="size-3.5 shrink-0" />
                    {{ __('Open in new tab') }}
                </a>
            </DropdownMenuItem>

            <DropdownMenuItem
                v-if="props.canDownload"
                as-child
                class="gap-2 text-xs"
            >
                <a
                    :href="props.file.download_url"
                    download
                    class="flex items-center gap-2"
                >
                    <Download class="size-3.5 shrink-0" />
                    {{ __('Download') }}
                </a>
            </DropdownMenuItem>

            <template v-if="props.canDelete && props.file.is_deletable">
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    :class="[
                        'gap-2 text-xs focus:bg-destructive/10',
                        confirmingDelete
                            ? 'text-destructive focus:text-destructive'
                            : 'text-destructive/80 focus:text-destructive',
                    ]"
                    @select.prevent="handleDelete"
                >
                    <Trash2 class="size-3.5 shrink-0" />
                    <span class="truncate">
                        {{
                            confirmingDelete
                                ? __('Click again to confirm')
                                : __('Delete file')
                        }}
                    </span>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
