<script setup lang="ts">
import { AlertTriangle, ArrowRight, Info, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import { index as appsRoute } from '@/routes/tyanc/apps';
import { index as filesRoute } from '@/routes/tyanc/files';
import { index as permissionsRoute } from '@/routes/tyanc/permissions';
import { index as rolesRoute } from '@/routes/tyanc/roles';
import { index as usersRoute } from '@/routes/tyanc/users';
import type { TyancDashboardAbilities, TyancDashboardAlert } from '@/types';

const props = defineProps<{
    alerts: TyancDashboardAlert[];
    abilities: TyancDashboardAbilities;
}>();

const { __ } = useTranslations();

const alertLink = (target: TyancDashboardAlert['target']) => {
    if (target === 'users' && props.abilities.users) {
        return usersRoute();
    }

    if (target === 'roles' && props.abilities.roles) {
        return rolesRoute();
    }

    if (target === 'permissions' && props.abilities.permissions) {
        return permissionsRoute();
    }

    if (target === 'files' && props.abilities.files) {
        return filesRoute();
    }

    if (target === 'apps' && props.abilities.apps) {
        return appsRoute();
    }

    return null;
};

const toneMeta = computed(() => ({
    danger: {
        icon: ShieldAlert,
        iconClass: 'text-red-600 dark:text-red-400',
        badgeClass:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300',
    },
    warning: {
        icon: AlertTriangle,
        iconClass: 'text-amber-600 dark:text-amber-400',
        badgeClass:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    info: {
        icon: Info,
        iconClass: 'text-sky-600 dark:text-sky-400',
        badgeClass:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
}));
</script>

<template>
    <Card class="border-sidebar-border/70 bg-background/80 shadow-none">
        <CardHeader class="space-y-1 pb-3">
            <div class="flex items-center justify-between gap-3">
                <CardTitle class="text-sm font-semibold text-foreground">
                    {{ __('Operational focus') }}
                </CardTitle>
                <Badge
                    variant="outline"
                    class="rounded-full text-xs tabular-nums"
                >
                    {{ alerts.length }}
                </Badge>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    __('Immediate governance signals from live platform data.')
                }}
            </p>
        </CardHeader>

        <CardContent class="space-y-3">
            <div
                v-if="alerts.length === 0"
                class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3"
            >
                <p
                    class="text-sm font-medium text-emerald-700 dark:text-emerald-300"
                >
                    {{ __('All tracked modules are aligned right now.') }}
                </p>
            </div>

            <div
                v-for="alert in alerts"
                :key="alert.key"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 p-4"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-sidebar-border/60 bg-background/90"
                    >
                        <component
                            :is="toneMeta[alert.tone].icon"
                            class="size-4"
                            :class="toneMeta[alert.tone].iconClass"
                        />
                    </div>

                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-foreground">
                                {{ __(alert.title) }}
                            </p>
                            <Badge
                                variant="outline"
                                class="rounded-full text-xs"
                                :class="toneMeta[alert.tone].badgeClass"
                            >
                                {{ __(alert.target) }}
                            </Badge>
                        </div>

                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ __(alert.description) }}
                        </p>

                        <TextLink
                            v-if="alertLink(alert.target)"
                            :href="alertLink(alert.target)!"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            {{ __('Open module') }}
                            <ArrowRight class="size-3" />
                        </TextLink>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
