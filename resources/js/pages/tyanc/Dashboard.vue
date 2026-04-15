<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    KeyRound,
    LayoutGrid,
    MessageSquareMore,
    Shield,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import AlertRail from '@/components/tyanc/dashboard/AlertRail.vue';
import AppsPanel from '@/components/tyanc/dashboard/AppsPanel.vue';
import FilesPanel from '@/components/tyanc/dashboard/FilesPanel.vue';
import ModuleCard from '@/components/tyanc/dashboard/ModuleCard.vue';
import PermissionsPanel from '@/components/tyanc/dashboard/PermissionsPanel.vue';
import RecentUsersPanel from '@/components/tyanc/dashboard/RecentUsersPanel.vue';
import RolesPanel from '@/components/tyanc/dashboard/RolesPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index as activityLogRoute } from '@/routes/tyanc/activity-log';
import { index as appsRoute } from '@/routes/tyanc/apps';
import { index as filesRoute } from '@/routes/tyanc/files';
import { index as messagesRoute } from '@/routes/tyanc/messages';
import { index as permissionsRoute } from '@/routes/tyanc/permissions';
import { index as rolesRoute } from '@/routes/tyanc/roles';
import { index as usersRoute } from '@/routes/tyanc/users';
import type {
    TyancDashboardModule,
    TyancDashboardProps,
    TyancDashboardSummary,
} from '@/types/tyanc/dashboard';

const props = defineProps<TyancDashboardProps>();

const { __ } = useTranslations();
const page = usePage();
const { dashboardBreadcrumbs } = useAppNavigation();

const iconMap: Record<TyancDashboardModule['key'], Component> = {
    users: Users,
    roles: Shield,
    permissions: KeyRound,
    files: Upload,
    apps: LayoutGrid,
};

const moduleLinks = computed(() => ({
    users: props.abilities.users ? usersRoute() : null,
    roles: props.abilities.roles ? rolesRoute() : null,
    permissions: props.abilities.permissions ? permissionsRoute() : null,
    files: props.abilities.files ? filesRoute() : null,
    apps: props.abilities.apps ? appsRoute() : null,
}));

const operationalState = computed(() => {
    if (props.summary.attention_count > 0) {
        return {
            label:
                props.summary.attention_count === 1
                    ? __('1 module needs intervention')
                    : __(':count modules need intervention', {
                          count: String(props.summary.attention_count),
                      }),
            dotClass: 'bg-red-500',
            textClass: 'text-red-600 dark:text-red-400',
        };
    }

    if (props.summary.monitoring_count > 0) {
        return {
            label:
                props.summary.monitoring_count === 1
                    ? __('1 module under observation')
                    : __(':count modules under observation', {
                          count: String(props.summary.monitoring_count),
                      }),
            dotClass: 'bg-amber-500',
            textClass: 'text-amber-600 dark:text-amber-400',
        };
    }

    return {
        label: __('All tracked modules are operating normally'),
        dotClass: 'bg-emerald-500',
        textClass: 'text-emerald-600 dark:text-emerald-400',
    };
});

const notificationsUnread = computed(() =>
    Number(page.props.notifications?.unread_count ?? 0),
);
const messagesUnread = computed(() =>
    Number(page.props.messagesUnreadCount ?? 0),
);

const summaryChips = computed(() => [
    {
        key: 'healthy',
        label: __('Healthy'),
        value: props.summary.healthy_count,
        className:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    {
        key: 'monitoring',
        label: __('Monitoring'),
        value: props.summary.monitoring_count,
        className:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    {
        key: 'attention',
        label: __('Attention'),
        value: props.summary.attention_count,
        className:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300',
    },
]);

const moduleCards = computed(() =>
    props.modules.map((module) => ({
        ...module,
        href: moduleLinks.value[module.key],
        icon: iconMap[module.key],
    })),
);

const summary = computed<TyancDashboardSummary>(() => props.summary);
</script>

<template>
    <Head :title="__('Dashboard')" />

    <AppLayout :breadcrumbs="dashboardBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_360px]">
                <Card
                    class="border-sidebar-border/70 bg-sidebar/25 shadow-none"
                >
                    <CardContent class="space-y-5 px-5 py-5 md:px-6">
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex size-2.5 rounded-full"
                                :class="operationalState.dotClass"
                                aria-hidden="true"
                            />
                            <span
                                class="text-sm font-medium"
                                :class="operationalState.textClass"
                            >
                                {{ operationalState.label }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                {{ __('Tyanc control plane') }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    __(
                                        'A live view of identity, governance, permissions, files, and registered apps across your admin foundation.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="chip in summaryChips"
                                :key="chip.key"
                                variant="outline"
                                class="rounded-full"
                                :class="chip.className"
                            >
                                {{ chip.label }} · {{ chip.value }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-3 pb-3">
                        <CardTitle
                            class="text-sm font-semibold text-foreground"
                        >
                            {{ __('Realtime signals') }}
                        </CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{
                                __(
                                    'Unread system activity and direct collaboration flow into the control plane.',
                                )
                            }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div
                                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                        >
                                            {{ __('Notifications') }}
                                        </p>
                                        <p
                                            class="pt-2 text-3xl font-semibold tracking-tight text-foreground tabular-nums"
                                        >
                                            {{ notificationsUnread }}
                                        </p>
                                    </div>
                                    <BellRing
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <Button
                                    v-if="abilities.activity_log"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-3 px-0"
                                    as-child
                                >
                                    <Link :href="activityLogRoute()">
                                        {{ __('Open activity log') }}
                                    </Link>
                                </Button>
                            </div>

                            <div
                                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                        >
                                            {{ __('Messages') }}
                                        </p>
                                        <p
                                            class="pt-2 text-3xl font-semibold tracking-tight text-foreground tabular-nums"
                                        >
                                            {{ messagesUnread }}
                                        </p>
                                    </div>
                                    <MessageSquareMore
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <Button
                                    v-if="abilities.messages"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-3 px-0"
                                    as-child
                                >
                                    <Link :href="messagesRoute()">
                                        {{ __('Open workspace') }}
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <span class="text-muted-foreground">
                                    {{ __('Tracked modules') }}
                                </span>
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{ summary.module_count }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <ModuleCard
                    v-for="module in moduleCards"
                    :key="module.key"
                    :title="__(module.title)"
                    :value="module.value"
                    :status="module.status"
                    :description="__(module.description)"
                    :metrics="
                        module.metrics.map((metric) => ({
                            label: __(metric.label),
                            value: metric.value,
                        }))
                    "
                    :icon="module.icon"
                    :href="module.href ?? undefined"
                />
            </section>

            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]"
            >
                <RecentUsersPanel
                    :users="users"
                    :can-open-users="abilities.users"
                />

                <div class="grid gap-4">
                    <AlertRail :alerts="alerts" :abilities="abilities" />
                    <PermissionsPanel
                        :permissions="permissions"
                        :can-open-permissions="abilities.permissions"
                    />
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <RolesPanel :roles="roles" :can-open-roles="abilities.roles" />
                <FilesPanel :files="files" :can-open-files="abilities.files" />
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <AppsPanel :apps="apps" :can-open-apps="abilities.apps" />

                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader class="space-y-3 pb-3">
                        <CardTitle
                            class="text-sm font-semibold text-foreground"
                        >
                            {{ __('Command links') }}
                        </CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{
                                __(
                                    'Jump directly into the core governance workspaces.',
                                )
                            }}
                        </p>
                    </CardHeader>
                    <CardContent class="grid gap-3 sm:grid-cols-2">
                        <Button
                            v-if="abilities.users"
                            variant="outline"
                            as-child
                        >
                            <Link :href="usersRoute()">{{ __('Users') }}</Link>
                        </Button>
                        <Button
                            v-if="abilities.roles"
                            variant="outline"
                            as-child
                        >
                            <Link :href="rolesRoute()">{{ __('Roles') }}</Link>
                        </Button>
                        <Button
                            v-if="abilities.permissions"
                            variant="outline"
                            as-child
                        >
                            <Link :href="permissionsRoute()">{{
                                __('Permissions')
                            }}</Link>
                        </Button>
                        <Button
                            v-if="abilities.files"
                            variant="outline"
                            as-child
                        >
                            <Link :href="filesRoute()">{{ __('Files') }}</Link>
                        </Button>
                        <Button
                            v-if="abilities.apps"
                            variant="outline"
                            as-child
                        >
                            <Link :href="appsRoute()">{{ __('Apps') }}</Link>
                        </Button>
                        <Button
                            v-if="abilities.activity_log"
                            variant="outline"
                            as-child
                        >
                            <Link :href="activityLogRoute()">{{
                                __('Activity log')
                            }}</Link>
                        </Button>
                        <Button
                            v-if="abilities.messages"
                            variant="outline"
                            as-child
                        >
                            <Link :href="messagesRoute()">{{
                                __('Messages')
                            }}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
