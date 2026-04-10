<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
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

const { __ } = useTranslations();
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ __('Effective access preview') }}</SheetTitle>
                <SheetDescription>
                    {{
                        __(
                            'Resolved roles, permissions, apps, and pages for the current preview selection.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 space-y-5 overflow-y-auto">
                <template v-if="props.preview">
                    <div class="space-y-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Roles') }}
                        </p>
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

                    <Separator />

                    <div class="space-y-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Effective permissions') }}
                        </p>

                        <div
                            v-if="props.preview.permissions.length > 0"
                            class="flex flex-wrap gap-1.5"
                        >
                            <Badge
                                v-for="permission in props.preview.permissions"
                                :key="permission"
                                variant="outline"
                                class="rounded-full font-mono text-xs"
                            >
                                {{ permission }}
                            </Badge>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ __('No permissions granted.') }}
                        </p>
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Accessible apps') }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                v-for="app in props.preview.accessible_apps"
                                :key="app.key"
                                variant="secondary"
                                class="rounded-full text-xs"
                            >
                                {{ app.label }}
                            </Badge>
                            <span
                                v-if="
                                    props.preview.accessible_apps.length === 0
                                "
                                class="text-sm text-muted-foreground"
                            >
                                {{ __('No accessible apps.') }}
                            </span>
                        </div>
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Accessible pages') }}
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="page in props.preview.accessible_pages"
                                :key="`${page.app_key}-${page.page_key}`"
                                class="rounded-lg border border-sidebar-border/60 px-3 py-2"
                            >
                                <p class="text-sm font-medium text-foreground">
                                    {{ page.page_label }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ page.app_label }}
                                </p>
                                <p
                                    v-if="page.permission_name"
                                    class="mt-1 font-mono text-xs text-muted-foreground"
                                >
                                    {{ page.permission_name }}
                                </p>
                            </div>
                            <p
                                v-if="
                                    props.preview.accessible_pages.length === 0
                                "
                                class="text-sm text-muted-foreground"
                            >
                                {{ __('No accessible pages.') }}
                            </p>
                        </div>
                    </div>
                </template>

                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-2 py-10 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{
                            __('Select a role to preview its effective access.')
                        }}
                    </p>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
