<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Clock3, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getInitials } from '@/composables/useInitials';
import { useTranslations } from '@/lib/translations';
import {
    index as usersRoute,
    show as userShowRoute,
} from '@/routes/tyanc/users';
import type { TyancDashboardUsers } from '@/types';

const props = defineProps<{
    users: TyancDashboardUsers;
    canOpenUsers: boolean;
}>();

const { __, locale } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
        }),
);

const statusBadgeClass = (status: string): string => {
    if (status === 'active') {
        return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'pending_verification') {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300';
};

const filters = computed(() => [
    {
        label: __('Active'),
        value: props.users.active,
        href: props.canOpenUsers
            ? usersRoute({ query: { filter: { status: 'active' } } })
            : null,
    },
    {
        label: __('Pending'),
        value: props.users.pending_verification,
        href: props.canOpenUsers
            ? usersRoute({
                  query: { filter: { status: 'pending_verification' } },
              })
            : null,
    },
    {
        label: __('Suspended'),
        value: props.users.suspended,
        href: props.canOpenUsers
            ? usersRoute({ query: { filter: { status: 'suspended' } } })
            : null,
    },
]);
</script>

<template>
    <Card class="border-sidebar-border/70 bg-background/80 shadow-none">
        <CardHeader class="space-y-3 pb-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                        {{ __('Recent users') }}
                    </CardTitle>
                    <p class="pt-1 text-sm text-muted-foreground">
                        {{ __('New and recently active identities in Tyanc.') }}
                    </p>
                </div>

                <Link
                    v-if="canOpenUsers"
                    :href="usersRoute()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                >
                    {{ __('Open users') }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <div class="flex flex-wrap gap-2">
                <component
                    :is="filter.href ? Link : 'div'"
                    v-for="filter in filters"
                    :key="filter.label"
                    v-bind="filter.href ? { href: filter.href } : {}"
                    class="rounded-full border border-sidebar-border/70 bg-sidebar/20 px-3 py-1.5 text-xs text-muted-foreground transition hover:border-primary/30 hover:text-foreground"
                >
                    <span>{{ filter.label }}</span>
                    <span
                        class="ml-1 font-semibold text-foreground tabular-nums"
                    >
                        {{ filter.value }}
                    </span>
                </component>
            </div>
        </CardHeader>

        <CardContent class="space-y-2">
            <div
                v-if="users.recent.length === 0"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-6 text-sm text-muted-foreground"
            >
                {{ __('No user activity recorded yet.') }}
            </div>

            <component
                :is="canOpenUsers ? Link : 'div'"
                v-for="user in users.recent"
                :key="user.id"
                v-bind="canOpenUsers ? { href: userShowRoute(user.id) } : {}"
                class="flex items-center gap-3 rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3 transition hover:border-primary/30 hover:bg-sidebar/30"
            >
                <Avatar class="size-10 border border-sidebar-border/70">
                    <AvatarImage
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="user.name"
                    />
                    <AvatarFallback>{{
                        getInitials(user.name)
                    }}</AvatarFallback>
                </Avatar>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ user.name }}
                        </p>
                        <Badge
                            variant="outline"
                            class="rounded-full text-[11px]"
                            :class="statusBadgeClass(user.status)"
                        >
                            {{ __(user.status.replaceAll('_', ' ')) }}
                        </Badge>
                    </div>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ user.email }}
                    </p>
                    <p class="truncate pt-1 text-xs text-muted-foreground">
                        {{ user.roles.join(', ') || __('No roles assigned') }}
                    </p>
                </div>

                <div
                    class="hidden shrink-0 items-center gap-1 text-xs text-muted-foreground md:flex"
                >
                    <Clock3 class="size-3" />
                    <span>
                        {{
                            user.last_login_at
                                ? dateFormatter.format(
                                      new Date(user.last_login_at),
                                  )
                                : dateFormatter.format(
                                      new Date(user.created_at),
                                  )
                        }}
                    </span>
                </div>

                <UserRound
                    class="size-4 shrink-0 text-muted-foreground md:hidden"
                />
            </component>
        </CardContent>
    </Card>
</template>
