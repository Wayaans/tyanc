<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronRight, Files, Folder, FolderOpen } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTranslations } from '@/lib/translations';
import { index } from '@/routes/tyanc/files';
import type { DataTableQuery, FileExplorer } from '@/types';

const props = defineProps<{
    explorer: FileExplorer;
    currentQuery: DataTableQuery;
    activeApp: string | null;
    activeFolder: string | null;
}>();

const { __ } = useTranslations();

const expandedApps = ref<Set<string>>(
    new Set(
        props.activeApp
            ? [props.activeApp]
            : props.explorer.apps.map((app) => app.key),
    ),
);

watch(
    () => props.activeApp,
    (app) => {
        if (app && !expandedApps.value.has(app)) {
            expandedApps.value = new Set(expandedApps.value).add(app);
        }
    },
);

function navigateTo(appKey?: string | null, folderPath?: string | null) {
    const filters = { ...props.currentQuery.filter };

    if (appKey) {
        filters.app_key = appKey;
    } else {
        delete filters.app_key;
    }

    if (folderPath) {
        filters.folder_path = folderPath;
    } else {
        delete filters.folder_path;
    }

    router.visit(
        index.url({
            query: {
                ...props.currentQuery,
                page: 1,
                filter: filters,
            },
        }),
        {
            method: 'get',
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['filesTable'],
        },
    );
}

function toggleExpand(key: string) {
    const next = new Set(expandedApps.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expandedApps.value = next;
}
</script>

<template>
    <nav class="flex flex-col gap-0.5" :aria-label="__('File explorer')">
        <p
            class="mb-1 px-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
        >
            {{ __('Explorer') }}
        </p>

        <Button
            variant="ghost"
            class="h-8 w-full justify-start gap-2 px-2 text-sm"
            :class="
                !activeApp
                    ? 'bg-sidebar/60 text-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
            @click="navigateTo(null, null)"
        >
            <Files class="size-3.5 shrink-0" />
            <span class="flex-1 truncate text-left">{{ __('All files') }}</span>
            <Badge
                variant="outline"
                class="ml-auto shrink-0 rounded-full font-mono text-xs tabular-nums"
            >
                {{ explorer.total_files }}
            </Badge>
        </Button>

        <Collapsible
            v-for="app in explorer.apps"
            :key="app.key"
            :open="expandedApps.has(app.key)"
            class="mt-0.5"
        >
            <div class="flex items-center gap-0.5">
                <Button
                    variant="ghost"
                    class="h-8 flex-1 justify-start gap-2 px-2 text-sm"
                    :class="
                        activeApp === app.key && !activeFolder
                            ? 'bg-sidebar/60 text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="navigateTo(app.key, null)"
                >
                    <FolderOpen
                        v-if="expandedApps.has(app.key)"
                        class="size-3.5 shrink-0"
                    />
                    <Folder v-else class="size-3.5 shrink-0" />
                    <span class="flex-1 truncate text-left">{{
                        app.label
                    }}</span>
                    <Badge
                        variant="outline"
                        class="ml-auto shrink-0 rounded-full font-mono text-xs tabular-nums"
                    >
                        {{ app.total_files }}
                    </Badge>
                </Button>

                <CollapsibleTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0 text-muted-foreground"
                        :aria-label="
                            expandedApps.has(app.key)
                                ? __('Collapse')
                                : __('Expand')
                        "
                        @click.stop="toggleExpand(app.key)"
                    >
                        <ChevronRight
                            class="size-3 transition-transform duration-150"
                            :class="
                                expandedApps.has(app.key) ? 'rotate-90' : ''
                            "
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>

            <CollapsibleContent>
                <div
                    v-if="app.folders.length > 0"
                    class="mt-0.5 ml-4 flex flex-col gap-0.5 border-l border-sidebar-border/50 pl-2"
                >
                    <button
                        v-for="folder in app.folders"
                        :key="folder.path"
                        type="button"
                        class="flex h-7 w-full items-center gap-2 rounded-md px-2 text-xs transition-colors"
                        :class="
                            activeApp === app.key &&
                            activeFolder === folder.path
                                ? 'bg-sidebar/60 font-medium text-foreground'
                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                        "
                        @click="navigateTo(app.key, folder.path)"
                    >
                        <Folder class="size-3 shrink-0" />
                        <span class="flex-1 truncate text-left">{{
                            folder.label
                        }}</span>
                        <span class="text-muted-foreground tabular-nums">{{
                            folder.total_files
                        }}</span>
                    </button>
                </div>

                <p
                    v-else
                    class="mt-0.5 ml-4 border-l border-sidebar-border/50 px-3 py-1 text-xs text-muted-foreground"
                >
                    {{ __('No folders') }}
                </p>
            </CollapsibleContent>
        </Collapsible>
    </nav>
</template>
