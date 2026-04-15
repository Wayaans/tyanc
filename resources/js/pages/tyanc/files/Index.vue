<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import {
    Database,
    Files,
    FolderOpen,
    HardDrive,
    LayoutGrid,
    List,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import FileExplorerSidebar from '@/components/tyanc/files/FileExplorerSidebar.vue';
import { createFileTableColumns } from '@/components/tyanc/files/FileTableColumns';
import FileUploadDropzone from '@/components/tyanc/files/FileUploadDropzone.vue';
import MediaLibraryGrid from '@/components/tyanc/files/MediaLibraryGrid.vue';
import MediaPreviewDialog from '@/components/tyanc/files/MediaPreviewDialog.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index } from '@/routes/tyanc/files';
import type {
    DataTablePayload,
    FileExplorer,
    FileExplorerAbilities,
    MediaFileRow,
} from '@/types';

const props = defineProps<{
    filesTable: DataTablePayload<MediaFileRow>;
    explorer: FileExplorer;
    abilities: FileExplorerAbilities;
}>();

const { __ } = useTranslations();
const { filesBreadcrumbs } = useAppNavigation();

const breadcrumbs = filesBreadcrumbs;

type ViewMode = 'grid' | 'list';

const viewMode = ref<ViewMode>('grid');
const previewFile = ref<MediaFileRow | null>(null);
const previewOpen = ref(false);

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

const columns = computed<ColumnDef<MediaFileRow>[]>(() =>
    createFileTableColumns(dateFormatter.value, openPreview, {
        canDownload: props.abilities.download,
        canDelete: props.abilities.delete,
    }),
);

const activeApp = computed<string | null>(
    () =>
        (props.filesTable.query.filter['app_key'] as string | undefined) ??
        null,
);

const activeFolder = computed<string | null>(
    () =>
        (props.filesTable.query.filter['folder_path'] as string | undefined) ??
        null,
);

const activeAppLabel = computed<string | null>(() => {
    if (!activeApp.value) {
        return null;
    }

    return (
        props.explorer.apps.find((a) => a.key === activeApp.value)?.label ??
        activeApp.value
    );
});

const activeFolderLabel = computed<string | null>(() => {
    if (!activeApp.value || !activeFolder.value) {
        return null;
    }

    const app = props.explorer.apps.find((a) => a.key === activeApp.value);
    const folder = app?.folders.find((f) => f.path === activeFolder.value);

    return folder?.label ?? activeFolder.value;
});

const contentHeading = computed<string>(() => {
    if (activeFolderLabel.value) {
        return activeFolderLabel.value;
    }

    if (activeAppLabel.value) {
        return activeAppLabel.value;
    }

    return __('All files');
});

function openPreview(file: MediaFileRow) {
    previewFile.value = file;
    previewOpen.value = true;
}
</script>

<template>
    <Head :title="__('Files')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Platform header -->
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Files') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Central file control plane — browse and manage uploads across all apps.',
                            )
                        }}
                    </p>
                </div>

                <!-- View mode toggle -->
                <div
                    class="flex shrink-0 items-center rounded-lg border border-sidebar-border/70 bg-background p-0.5"
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 rounded-md"
                        :class="
                            viewMode === 'grid'
                                ? 'bg-sidebar/60 text-foreground shadow-sm'
                                : 'text-muted-foreground'
                        "
                        :aria-label="__('Grid view')"
                        @click="viewMode = 'grid'"
                    >
                        <LayoutGrid class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 rounded-md"
                        :class="
                            viewMode === 'list'
                                ? 'bg-sidebar/60 text-foreground shadow-sm'
                                : 'text-muted-foreground'
                        "
                        :aria-label="__('List view')"
                        @click="viewMode = 'list'"
                    >
                        <List class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Platform summary stats -->
            <div
                class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <Files class="size-4 text-muted-foreground" />
                    <span class="text-sm font-medium text-foreground">
                        {{ explorer.total_files.toLocaleString() }}
                    </span>
                    <span class="text-sm text-muted-foreground">{{
                        __('files')
                    }}</span>
                </div>

                <div class="hidden h-3.5 w-px bg-sidebar-border/70 sm:block" />

                <div class="flex items-center gap-2">
                    <HardDrive class="size-4 text-muted-foreground" />
                    <span class="text-sm font-medium text-foreground">
                        {{ explorer.total_size_human }}
                    </span>
                    <span class="text-sm text-muted-foreground">{{
                        __('used')
                    }}</span>
                </div>

                <div class="hidden h-3.5 w-px bg-sidebar-border/70 sm:block" />

                <div class="flex items-center gap-2">
                    <Database class="size-4 text-muted-foreground" />
                    <span class="text-sm font-medium text-foreground">
                        {{ explorer.app_count }}
                    </span>
                    <span class="text-sm text-muted-foreground">{{
                        __('apps')
                    }}</span>
                </div>

                <div class="hidden h-3.5 w-px bg-sidebar-border/70 sm:block" />

                <div class="flex items-center gap-2">
                    <FolderOpen class="size-4 text-muted-foreground" />
                    <span class="text-sm font-medium text-foreground">
                        {{ explorer.folder_count }}
                    </span>
                    <span class="text-sm text-muted-foreground">{{
                        __('folders')
                    }}</span>
                </div>
            </div>

            <!-- Upload dropzone — scoped to Tyanc shared library -->
            <div>
                <p
                    class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ __('Upload to Tyanc shared library') }}
                </p>
                <FileUploadDropzone />
            </div>

            <Separator />

            <!-- Explorer: sidebar + content -->
            <div class="flex flex-col gap-5 md:flex-row md:gap-6">
                <!-- Sidebar -->
                <aside class="w-full shrink-0 md:w-56">
                    <FileExplorerSidebar
                        :explorer="explorer"
                        :current-query="filesTable.query"
                        :active-app="activeApp"
                        :active-folder="activeFolder"
                    />
                </aside>

                <!-- Content area -->
                <div class="min-w-0 flex-1">
                    <!-- Context heading -->
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ contentHeading }}
                        </h2>
                        <span
                            v-if="filesTable.meta.total > 0"
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            {{
                                __(':count files', {
                                    count: String(filesTable.meta.total),
                                })
                            }}
                        </span>
                    </div>

                    <!-- Grid view -->
                    <MediaLibraryGrid
                        v-if="viewMode === 'grid'"
                        :files="filesTable.rows"
                        :can-download="props.abilities.download"
                        :can-delete="props.abilities.delete"
                        @preview="openPreview"
                    />

                    <!-- List / table view -->
                    <DataTable
                        v-else
                        :columns="columns"
                        :rows="filesTable.rows"
                        :meta="filesTable.meta"
                        :query="filesTable.query"
                        :filters="filesTable.filters"
                        :route="index"
                        :only="['filesTable']"
                        :empty-title="__('No files found.')"
                        :empty-description="
                            __('No files match the current explorer view.')
                        "
                    />
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Preview dialog -->
    <MediaPreviewDialog
        v-model:open="previewOpen"
        :file="previewFile"
        :can-download="props.abilities.download"
    />
</template>
