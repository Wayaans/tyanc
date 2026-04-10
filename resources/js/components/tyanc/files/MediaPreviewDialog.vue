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
}>();

const open = defineModel<boolean>('open', { default: false });

const { __ } = useTranslations();

const isImage = computed(
    () => props.file?.mime_type.startsWith('image/') ?? false,
);
const isVideo = computed(
    () => props.file?.mime_type.startsWith('video/') ?? false,
);

const fileSizeFormatted = computed(() => props.file?.size_human ?? '—');

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const uploadedAt = computed(() =>
    props.file ? dateFormatter.format(new Date(props.file.created_at)) : '—',
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-3xl gap-0 overflow-hidden p-0">
            <DialogHeader
                class="flex-row items-center justify-between border-b border-sidebar-border/70 p-4"
            >
                <DialogTitle class="truncate text-sm font-medium">
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
                        v-if="props.file"
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
                    </div>
                </div>

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

                            <div v-if="props.file.collection_name">
                                <dt class="text-xs text-muted-foreground">
                                    {{ __('Collection') }}
                                </dt>
                                <dd class="mt-0.5 text-xs text-foreground">
                                    {{ props.file.collection_name }}
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
                                    {{ __('Created at') }}
                                </dt>
                                <dd class="mt-0.5 text-xs text-foreground">
                                    {{ uploadedAt }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
