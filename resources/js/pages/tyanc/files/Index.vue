<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { LayoutGrid, List } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/admin/DataTable.vue';
import { createFileTableColumns } from '@/components/tyanc/files/FileTableColumns';
import FileUploadDropzone from '@/components/tyanc/files/FileUploadDropzone.vue';
import MediaLibraryGrid from '@/components/tyanc/files/MediaLibraryGrid.vue';
import MediaPreviewDialog from '@/components/tyanc/files/MediaPreviewDialog.vue';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index } from '@/routes/tyanc/files';
import type { DataTablePayload, MediaFileRow } from '@/types';

const props = defineProps<{
    filesTable: DataTablePayload<MediaFileRow>;
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
    createFileTableColumns(dateFormatter.value, openPreview),
);

function openPreview(file: MediaFileRow) {
    previewFile.value = file;
    previewOpen.value = true;
}

function handleUploaded() {
    // Inertia router.post in the dropzone component triggers a page reload
    // which refreshes props automatically.
}
</script>

<template>
    <Head :title="__('Files')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('Files') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Manage uploaded assets and shared documents from one library.',
                            )
                        }}
                    </p>
                </div>

                <!-- View mode toggle -->
                <div
                    class="flex items-center rounded-lg border border-sidebar-border/70 bg-background p-0.5"
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

            <!-- Upload dropzone -->
            <FileUploadDropzone @uploaded="handleUploaded" />

            <!-- Grid view -->
            <MediaLibraryGrid
                v-if="viewMode === 'grid'"
                :files="props.filesTable.rows as MediaFileRow[]"
                @preview="openPreview"
            />

            <!-- List / table view -->
            <DataTable
                v-else
                :columns="columns"
                :rows="props.filesTable.rows"
                :meta="props.filesTable.meta"
                :query="props.filesTable.query"
                :filters="props.filesTable.filters"
                :route="index"
                :only="['filesTable']"
                :empty-title="__('No files found.')"
                :empty-description="
                    __('Upload your first file to start the shared library.')
                "
            />
        </div>
    </AppLayout>

    <!-- Preview dialog -->
    <MediaPreviewDialog v-model:open="previewOpen" :file="previewFile" />
</template>
