<script setup lang="ts">
import { AppWindow, FileText, Key, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/lib/translations';
import type { EffectiveAccessData } from '@/types';

const props = defineProps<{
    open: boolean;
    preview: EffectiveAccessData | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

type PageGroup = {
    appKey: string;
    appLabel: string;
    pages: EffectiveAccessData['accessible_pages'];
};

const pagesByApp = computed<PageGroup[]>(() => {
    if (!props.preview) return [];

    const appOrder = new Map(
        props.preview.accessible_apps.map((app, index) => [app.key, index]),
    );
    const groups = new Map<string, PageGroup>();
    const sortedPages = [...props.preview.accessible_pages].sort(
        (left, right) => left.page_label.localeCompare(right.page_label),
    );

    for (const page of sortedPages) {
        if (!groups.has(page.app_key)) {
            groups.set(page.app_key, {
                appKey: page.app_key,
                appLabel: page.app_label,
                pages: [],
            });
        }
        groups.get(page.app_key)!.pages.push(page);
    }

    return Array.from(groups.values()).sort((left, right) => {
        const leftOrder = appOrder.get(left.appKey) ?? Number.MAX_SAFE_INTEGER;
        const rightOrder =
            appOrder.get(right.appKey) ?? Number.MAX_SAFE_INTEGER;

        if (leftOrder !== rightOrder) {
            return leftOrder - rightOrder;
        }

        return left.appLabel.localeCompare(right.appLabel);
    });
});

const { __ } = useTranslations();
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent
            data-testid="effective-access-preview-sheet"
            class="flex w-full flex-col overflow-hidden sm:max-w-xl"
        >
            <SheetHeader class="shrink-0 px-6 pt-6">
                <SheetTitle>{{ __('Effective access preview') }}</SheetTitle>
            </SheetHeader>

            <div class="flex-1 space-y-5 overflow-y-auto px-6 pb-6">
                <template v-if="props.preview">
                    <!-- Summary hero -->
                    <div
                        data-testid="effective-access-summary"
                        class="space-y-3 rounded-xl border bg-muted/40 px-4 py-3"
                    >
                        <div>
                            <p
                                class="text-sm leading-tight font-semibold text-foreground"
                            >
                                {{
                                    props.preview.role_name ??
                                    __('Combined access')
                                }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ __('Resolved effective access') }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap gap-x-4 gap-y-1.5 border-t border-border/50 pt-2.5"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Shield class="size-3 text-primary/70" />
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{ props.preview.roles.length }}
                                </span>
                                {{ __('roles') }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Key class="size-3 text-primary/70" />
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{ props.preview.permissions.length }}
                                </span>
                                {{ __('permissions') }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <AppWindow class="size-3 text-primary/70" />
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{ props.preview.accessible_apps.length }}
                                </span>
                                {{ __('apps') }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <FileText class="size-3 text-primary/70" />
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{ props.preview.accessible_pages.length }}
                                </span>
                                {{ __('pages') }}
                            </span>
                        </div>
                    </div>

                    <!-- Roles -->
                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5">
                            <Shield
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Roles') }}
                            </p>
                            <Badge
                                variant="outline"
                                class="ml-auto h-4 px-1.5 text-[10px]"
                            >
                                {{ props.preview.roles.length }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                v-for="role in props.preview.roles"
                                :key="role"
                                variant="outline"
                                class="rounded-full text-xs"
                            >
                                {{ role }}
                            </Badge>
                            <span
                                v-if="props.preview.roles.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                {{ __('No roles selected.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class="space-y-2.5 rounded-xl border bg-muted/20 p-3">
                        <div class="flex items-center gap-1.5">
                            <Key
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Effective permissions') }}
                            </p>
                            <Badge
                                variant="outline"
                                class="ml-auto h-4 px-1.5 text-[10px]"
                            >
                                {{ props.preview.permissions.length }}
                            </Badge>
                        </div>
                        <div
                            v-if="props.preview.permissions.length > 0"
                            class="flex flex-wrap gap-1"
                        >
                            <Badge
                                v-for="permission in props.preview.permissions"
                                :key="permission"
                                variant="outline"
                                class="rounded px-1.5 font-mono text-[10px]"
                            >
                                {{ permission }}
                            </Badge>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">
                            {{ __('No permissions granted.') }}
                        </p>
                    </div>

                    <!-- Accessible apps -->
                    <div data-testid="effective-access-apps" class="space-y-2">
                        <div class="flex items-center gap-1.5">
                            <AppWindow
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Accessible apps') }}
                            </p>
                            <Badge
                                variant="outline"
                                class="ml-auto h-4 px-1.5 text-[10px]"
                            >
                                {{ props.preview.accessible_apps.length }}
                            </Badge>
                        </div>
                        <div
                            v-if="props.preview.accessible_apps.length > 0"
                            class="grid grid-cols-1 gap-1.5 sm:grid-cols-2"
                        >
                            <div
                                v-for="app in props.preview.accessible_apps"
                                :key="app.key"
                                :data-testid="`effective-access-app-${app.key}`"
                                class="rounded-lg border bg-background px-3 py-2"
                            >
                                <p
                                    :data-testid="`effective-access-app-label-${app.key}`"
                                    class="truncate text-xs font-medium text-foreground"
                                >
                                    {{ app.label }}
                                </p>
                                <p
                                    class="truncate font-mono text-[10px] text-muted-foreground"
                                >
                                    {{ app.key }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ __('No accessible apps.') }}
                        </p>
                    </div>

                    <!-- Accessible pages grouped by app -->
                    <div data-testid="effective-access-pages" class="space-y-2">
                        <div class="flex items-center gap-1.5">
                            <FileText
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Accessible pages') }}
                            </p>
                            <Badge
                                variant="outline"
                                class="ml-auto h-4 px-1.5 text-[10px]"
                            >
                                {{ props.preview.accessible_pages.length }}
                            </Badge>
                        </div>
                        <div
                            v-if="pagesByApp.length > 0"
                            class="grid gap-3 md:grid-cols-2"
                        >
                            <div
                                v-for="group in pagesByApp"
                                :key="group.appKey"
                                :data-testid="`effective-access-page-group-${group.appKey}`"
                                class="space-y-1.5"
                            >
                                <div class="flex items-center gap-2 px-0.5">
                                    <p
                                        class="text-[10px] font-semibold tracking-wider text-muted-foreground/70 uppercase"
                                    >
                                        {{ group.appLabel }}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        class="h-4 px-1.5 text-[10px]"
                                    >
                                        {{ group.pages.length }}
                                    </Badge>
                                </div>
                                <div
                                    class="divide-y overflow-hidden rounded-xl border"
                                >
                                    <div
                                        v-for="page in group.pages"
                                        :key="`${page.app_key}-${page.page_key}`"
                                        class="bg-background px-3 py-2"
                                    >
                                        <p
                                            :data-testid="`effective-access-page-label-${page.app_key}-${page.page_key}`"
                                            class="truncate text-xs font-medium text-foreground"
                                        >
                                            {{ page.page_label }}
                                        </p>
                                        <p
                                            v-if="page.permission_name"
                                            class="truncate font-mono text-[10px] text-muted-foreground"
                                        >
                                            {{ page.permission_name }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ __('No accessible pages.') }}
                        </p>
                    </div>
                </template>

                <!-- Empty state -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-2 py-16 text-center"
                >
                    <div class="mb-1 rounded-full border bg-muted/50 p-3">
                        <Shield class="size-5 text-muted-foreground/40" />
                    </div>
                    <p class="text-sm font-medium text-foreground">
                        {{ __('No selection') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            __('Select a role to preview its effective access.')
                        }}
                    </p>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
