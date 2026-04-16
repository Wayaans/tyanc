<script setup lang="ts">
import { Download, ExternalLink, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/lib/translations';
import type { MediaFileRow } from '@/types';

const props = defineProps<{
    file: MediaFileRow | null;
    canDownload: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const { __ } = useTranslations();

const isImage = computed(() => props.file?.is_image ?? false);
const isVideo = computed(
    () => props.file?.mime_type.startsWith('video/') ?? false,
);
const isPdf = computed(() => props.file?.mime_type === 'application/pdf');

const fileSizeFormatted = computed(() => props.file?.size_human ?? '—');

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const uploadedAt = computed(() =>
    props.file ? dateFormatter.format(new Date(props.file.created_at)) : '—',
);

function sourceLabel(source: string): string {
    if (source === 'media_library') {
        return __('Media Library');
    }

    if (source === 'public_disk') {
        return __('Public Disk');
    }

    return source;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="max-w-3xl gap-0 overflow-hidden p-0"
        >
            <DialogHeader
                class="flex-row items-center justify-between gap-3 border-b border-sidebar-border/70 p-4"
            >
                <DialogTitle
                    class="min-w-0 flex-1 truncate text-sm font-medium"
                >
                    {{ props.file?.name ?? __('Preview file') }}
                </DialogTitle>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="props.file"
                        as="a"
                        :href="props.file.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-label="__('Open in new tab')"
                    >
                        <ExternalLink class="size-4" />
                    </Button>

                    <Button
                        v-if="props.file && props.canDownload"
                        as="a"
                        :href="props.file.download_url"
                        download
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-label="__('Download')"
                    >
                        <Download class="size-4" />
                    </Button>

                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-label="__('Close')"
                        @click="open = false"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </DialogHeader>

            <div v-if="props.file" class="flex flex-col md:flex-row">
                <!-- Preview area -->
                <div
                    class="flex min-h-64 items-center justify-center bg-muted/30 md:w-2/3"
                >
                    <img
                        v-if="isImage"
                        :src="props.file.url"
                        :alt="props.file.file_name"
                        class="max-h-[480px] max-w-full object-contain"
                    />

                    <video
                        v-else-if="isVideo"
                        :src="props.file.url"
                        controls
                        class="max-h-[480px] max-w-full"
                    />

                    <iframe
                        v-else-if="isPdf"
                        :src="props.file.url"
                        class="h-[480px] w-full border-0"
                        :title="props.file.file_name"
                        sandbox="allow-scripts allow-same-origin"
                    />

                    <div
                        v-else
                        class="flex flex-col items-center gap-3 p-8 text-muted-foreground"
                    >
                        <div
                            class="flex size-16 items-center justify-center rounded-2xl border border-sidebar-border/70 bg-background"
                        >
                            <span
                                class="font-mono text-sm font-medium uppercase"
                            >
                                {{ props.file.extension ?? '?' }}
                            </span>
                        </div>
                        <p class="text-sm">{{ __('No preview available') }}</p>
                        <Button
                            v-if="props.canDownload"
                            as="a"
                            :href="props.file.download_url"
                            download
                            variant="outline"
                            size="sm"
                            class="gap-2"
                        >
                            <Download class="size-3.5" />
                            {{ __('Download to view') }}
                        </Button>
                    </div>
                </div>

                <!-- Details panel -->
                <div
                    class="flex flex-col gap-0 border-t border-sidebar-border/70 md:w-1/3 md:border-t-0 md:border-l"
                >
                    <div class="p-4">
                        <h3
                            class="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            {{ __('File details') }}
                        </h3>

                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('File name') }}
                                </dt>
                                <dd
                                    class="mt-0.5 font-mono text-xs break-all text-foreground"
                                >
                                    {{ props.file.file_name }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('File type') }}
                                </dt>
                                <dd class="mt-0.5">
                                    <Badge
                                        variant="outline"
                                        class="rounded-full font-mono text-xs"
                                    >
                                        {{ props.file.mime_type }}
                                    </Badge>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('File size') }}
                                </dt>
                                <dd
                                    class="mt-0.5 text-xs font-medium text-foreground tabular-nums"
                                >
                                    {{ fileSizeFormatted }}
                                </dd>
                            </div>

                            <div v-if="props.file.app_label">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('App') }}
                                </dt>
                                <dd class="mt-0.5">
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full text-xs"
                                    >
                                        {{ props.file.app_label }}
                                    </Badge>
                                </dd>
                            </div>

                            <div v-if="props.file.folder_label">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Folder') }}
                                </dt>
                                <dd class="mt-0.5 text-xs text-foreground">
                                    {{ props.file.folder_label }}
                                </dd>
                            </div>

                            <div v-if="props.file.source">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Source') }}
                                </dt>
                                <dd class="mt-0.5 text-xs text-foreground">
                                    {{ sourceLabel(props.file.source) }}
                                </dd>
                            </div>

                            <div v-if="props.file.storage_path">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Storage path') }}
                                </dt>
                                <dd
                                    class="mt-0.5 font-mono text-xs break-all text-muted-foreground"
                                >
                                    {{ props.file.storage_path }}
                                </dd>
                            </div>

                            <div v-if="props.file.uploaded_by_name">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Uploaded by') }}
                                </dt>
                                <dd class="mt-0.5 text-xs text-foreground">
                                    {{ props.file.uploaded_by_name }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <Separator />

                    <div class="p-4">
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Uploaded at') }}
                                </dt>
                                <dd class="mt-0.5 text-xs text-foreground">
                                    {{ uploadedAt }}
                                </dd>
                            </div>

                            <div v-if="props.file.disk">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Storage disk') }}
                                </dt>
                                <dd
                                    class="mt-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    {{ props.file.disk }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
