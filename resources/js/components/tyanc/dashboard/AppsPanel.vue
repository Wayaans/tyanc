<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, LayoutGrid, Map } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import { index as appsRoute } from '@/routes/tyanc/apps';
import type { TyancDashboardApps } from '@/types';

const props = defineProps<{
    apps: TyancDashboardApps;
    canOpenApps: boolean;
}>();

const { __ } = useTranslations();
</script>

<template>
    <Card class="border-sidebar-border/70 bg-background/80 shadow-none">
        <CardHeader class="space-y-3 pb-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                        {{ __('App registry') }}
                    </CardTitle>
                    <p class="pt-1 text-sm text-muted-foreground">
                        {{
                            __(
                                'Registry state for installed apps and navigable pages.',
                            )
                        }}
                    </p>
                </div>

                <Link
                    v-if="canOpenApps"
                    :href="appsRoute()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                >
                    {{ __('Open apps') }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <component
                    :is="canOpenApps ? Link : 'div'"
                    v-bind="
                        canOpenApps
                            ? {
                                  href: appsRoute({
                                      query: { filter: { status: 'enabled' } },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Enabled') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ apps.enabled }}
                    </p>
                </component>
                <component
                    :is="canOpenApps ? Link : 'div'"
                    v-bind="
                        canOpenApps
                            ? {
                                  href: appsRoute({
                                      query: { filter: { status: 'disabled' } },
                                  }),
                              }
                            : {}
                    "
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Disabled') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ apps.disabled }}
                    </p>
                </component>
                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Pages') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ apps.pages }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-2">
            <div
                v-if="apps.recent.length === 0"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-6 text-sm text-muted-foreground"
            >
                {{ __('No apps are registered yet.') }}
            </div>

            <component
                :is="canOpenApps ? Link : 'div'"
                v-for="app in apps.recent"
                :key="app.id"
                v-bind="canOpenApps ? { href: appsRoute() } : {}"
                class="flex items-center gap-3 rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3 transition hover:border-primary/30 hover:bg-sidebar/30"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-sidebar-border/60 bg-background/90"
                >
                    <LayoutGrid class="size-4 text-muted-foreground" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ app.label }}
                        </p>
                        <Badge
                            variant="outline"
                            class="rounded-full text-[11px]"
                            :class="
                                app.enabled
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                    : 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                            "
                        >
                            {{ app.enabled ? __('Enabled') : __('Disabled') }}
                        </Badge>
                        <Badge
                            v-if="app.is_system"
                            variant="outline"
                            class="rounded-full text-[11px]"
                        >
                            {{ __('System') }}
                        </Badge>
                    </div>
                    <p class="truncate pt-1 text-sm text-muted-foreground">
                        /{{ app.route_prefix }}
                    </p>
                </div>

                <div
                    class="hidden text-right text-xs text-muted-foreground md:block"
                >
                    <p class="text-foreground tabular-nums">
                        {{ app.page_count }}
                    </p>
                    <p>{{ __('pages') }}</p>
                </div>

                <Map class="size-4 shrink-0 text-muted-foreground md:hidden" />
            </component>
        </CardContent>
    </Card>
</template>
