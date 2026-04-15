<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, KeyRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import { index as permissionsRoute } from '@/routes/tyanc/permissions';
import type { TyancDashboardPermissions } from '@/types';

const props = defineProps<{
    permissions: TyancDashboardPermissions;
    canOpenPermissions: boolean;
}>();

const { __ } = useTranslations();

const syncedWidth = computed(() => {
    if (props.permissions.source_total === 0) {
        return 0;
    }

    return (props.permissions.synced / props.permissions.source_total) * 100;
});

const missingWidth = computed(() => {
    if (props.permissions.source_total === 0) {
        return 0;
    }

    return (props.permissions.missing / props.permissions.source_total) * 100;
});

const orphanedWidth = computed(() => {
    if (props.permissions.total === 0) {
        return 0;
    }

    return (props.permissions.orphaned / props.permissions.total) * 100;
});

const syncBadgeClass = (status: string): string => {
    if (status === 'synced') {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'missing') {
        return 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300';
    }

    return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
};
</script>

<template>
    <Card class="border-sidebar-border/70 bg-background/80 shadow-none">
        <CardHeader class="space-y-3 pb-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                        {{ __('Permission sync') }}
                    </CardTitle>
                    <p class="pt-1 text-sm text-muted-foreground">
                        {{
                            __(
                                'Database permissions aligned against the source of truth.',
                            )
                        }}
                    </p>
                </div>

                <Link
                    v-if="canOpenPermissions"
                    :href="permissionsRoute()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                >
                    {{ __('Open permissions') }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <div class="space-y-2">
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>{{ __('Source coverage') }}</span>
                    <span class="text-foreground tabular-nums">
                        {{ permissions.synced }}/{{ permissions.source_total }}
                    </span>
                </div>
                <div
                    class="flex h-2 w-full overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full bg-emerald-500"
                        :style="`width: ${syncedWidth}%`"
                    />
                    <div
                        class="h-full bg-red-500"
                        :style="`width: ${missingWidth}%`"
                    />
                    <div
                        class="h-full bg-amber-500"
                        :style="`width: ${orphanedWidth}%`"
                    />
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <component
                    :is="canOpenPermissions ? Link : 'div'"
                    v-bind="
                        canOpenPermissions
                            ? {
                                  href: permissionsRoute({
                                      query: { filter: { status: 'synced' } },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Synced') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        {{ permissions.synced }}
                    </p>
                </component>
                <component
                    :is="canOpenPermissions ? Link : 'div'"
                    v-bind="
                        canOpenPermissions
                            ? {
                                  href: permissionsRoute({
                                      query: { filter: { status: 'missing' } },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Missing') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-red-600 tabular-nums dark:text-red-400"
                    >
                        {{ permissions.missing }}
                    </p>
                </component>
                <component
                    :is="canOpenPermissions ? Link : 'div'"
                    v-bind="
                        canOpenPermissions
                            ? {
                                  href: permissionsRoute({
                                      query: { filter: { status: 'orphaned' } },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Orphaned') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-amber-600 tabular-nums dark:text-amber-400"
                    >
                        {{ permissions.orphaned }}
                    </p>
                </component>
            </div>
        </CardHeader>

        <CardContent class="space-y-3">
            <div
                v-if="permissions.top.length === 0"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-6 text-sm text-muted-foreground"
            >
                {{
                    __('No permissions have been synced into the database yet.')
                }}
            </div>

            <div
                v-for="permission in permissions.top"
                :key="permission.name"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ permission.resource_label }}
                                <span class="text-muted-foreground"
                                    >· {{ permission.action_label }}</span
                                >
                            </p>
                            <Badge
                                variant="outline"
                                class="rounded-full text-[11px]"
                                :class="syncBadgeClass(permission.sync_status)"
                            >
                                {{ __(permission.sync_status) }}
                            </Badge>
                        </div>
                        <p class="truncate pt-1 text-sm text-muted-foreground">
                            {{ permission.app_label }} · {{ permission.name }}
                        </p>
                    </div>

                    <span
                        class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <KeyRound class="size-3" />
                        <span class="tabular-nums">{{
                            permission.role_count
                        }}</span>
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
