<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileText,
    FolderArchive,
    ImageIcon,
    Paperclip,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import { index as filesRoute } from '@/routes/tyanc/files';
import type { TyancDashboardFiles } from '@/types';

const props = defineProps<{
    files: TyancDashboardFiles;
    canOpenFiles: boolean;
}>();

const { __, locale } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
        }),
);

const fileIcon = (mimeGroup: string) => {
    if (mimeGroup === 'image') {
        return ImageIcon;
    }

    if (mimeGroup === 'application' || mimeGroup === 'text') {
        return FileText;
    }

    return Paperclip;
};
</script>

<template>
    <Card class="border-sidebar-border/70 bg-background/80 shadow-none">
        <CardHeader class="space-y-3 pb-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                        {{ __('File library') }}
                    </CardTitle>
                    <p class="pt-1 text-sm text-muted-foreground">
                        {{
                            __(
                                'Platform-wide files, uploads, and shared assets.',
                            )
                        }}
                    </p>
                </div>

                <Link
                    v-if="canOpenFiles"
                    :href="filesRoute()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                >
                    {{ __('Open files') }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Storage') }}
                    </p>
                    <p class="pt-1 text-sm font-semibold text-foreground">
                        {{ files.total_size_human }}
                    </p>
                </div>
                <component
                    :is="canOpenFiles ? Link : 'div'"
                    v-bind="
                        canOpenFiles
                            ? {
                                  href: filesRoute({
                                      query: {
                                          filter: { mime_group: 'image' },
                                      },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Images') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ files.images }}
                    </p>
                </component>
                <component
                    :is="canOpenFiles ? Link : 'div'"
                    v-bind="
                        canOpenFiles
                            ? {
                                  href: filesRoute({
                                      query: {
                                          filter: { mime_group: 'application' },
                                      },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Documents') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ files.documents }}
                    </p>
                </component>
            </div>
        </CardHeader>

        <CardContent class="space-y-2">
            <div
                v-if="files.recent.length === 0"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-6 text-sm text-muted-foreground"
            >
                {{ __('No platform files uploaded yet.') }}
            </div>

            <component
                :is="canOpenFiles ? Link : 'div'"
                v-for="file in files.recent"
                :key="file.id"
                v-bind="canOpenFiles ? { href: filesRoute() } : {}"
                class="flex items-center gap-3 rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3 transition hover:border-primary/30 hover:bg-sidebar/30"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-sidebar-border/60 bg-background/90"
                >
                    <component
                        :is="fileIcon(file.mime_group)"
                        class="size-4 text-muted-foreground"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ file.name }}
                        </p>
                        <Badge
                            variant="outline"
                            class="rounded-full text-[11px]"
                        >
                            {{ file.extension ?? __('file') }}
                        </Badge>
                    </div>
                    <p class="truncate pt-1 text-sm text-muted-foreground">
                        {{ file.uploaded_by_name || __('System upload') }}
                    </p>
                </div>

                <div
                    class="hidden text-right text-xs text-muted-foreground md:block"
                >
                    <p class="text-foreground tabular-nums">
                        {{ file.size_human }}
                    </p>
                    <p>{{ dateFormatter.format(new Date(file.created_at)) }}</p>
                </div>

                <FolderArchive
                    class="size-4 shrink-0 text-muted-foreground md:hidden"
                />
            </component>
        </CardContent>
    </Card>
</template>
