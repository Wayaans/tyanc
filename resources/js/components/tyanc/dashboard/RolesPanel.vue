<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Shield, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import { index as rolesRoute } from '@/routes/tyanc/roles';
import type { TyancDashboardRoles } from '@/types';

const props = defineProps<{
    roles: TyancDashboardRoles;
    canOpenRoles: boolean;
}>();

const { __ } = useTranslations();

const maxUsers = computed(() =>
    Math.max(...props.roles.top.map((role) => role.user_count), 1),
);
</script>

<template>
    <Card class="border-sidebar-border/70 bg-background/80 shadow-none">
        <CardHeader class="space-y-3 pb-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <CardTitle class="text-sm font-semibold text-foreground">
                        {{ __('Role coverage') }}
                    </CardTitle>
                    <p class="pt-1 text-sm text-muted-foreground">
                        {{
                            __(
                                'How governance roles are distributed across users and permissions.',
                            )
                        }}
                    </p>
                </div>

                <Link
                    v-if="canOpenRoles"
                    :href="rolesRoute()"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary"
                >
                    {{ __('Open roles') }}
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Reserved') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ roles.reserved }}
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('With permissions') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ roles.with_permissions }}
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-3 py-2"
                >
                    <p
                        class="text-[11px] tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Without permissions') }}
                    </p>
                    <p
                        class="pt-1 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ roles.without_permissions }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-3">
            <div
                v-if="roles.top.length === 0"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-6 text-sm text-muted-foreground"
            >
                {{ __('No roles have been created yet.') }}
            </div>

            <div
                v-for="role in roles.top"
                :key="role.id"
                class="rounded-2xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ role.name }}
                            </p>
                            <Badge
                                v-if="role.is_reserved"
                                variant="outline"
                                class="rounded-full text-[11px]"
                            >
                                {{ __('Reserved') }}
                            </Badge>
                        </div>
                        <div
                            class="flex flex-wrap gap-4 pt-1 text-xs text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1">
                                <Users class="size-3" />
                                <span class="tabular-nums">{{
                                    role.user_count
                                }}</span>
                                {{ __('users') }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Shield class="size-3" />
                                <span class="tabular-nums">{{
                                    role.permission_count
                                }}</span>
                                {{ __('permissions') }}
                            </span>
                            <span>{{ __('Level') }} {{ role.level }}</span>
                        </div>
                    </div>
                    <span
                        class="shrink-0 text-xs font-medium text-muted-foreground tabular-nums"
                    >
                        {{ role.user_count }}
                    </span>
                </div>

                <div
                    class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full rounded-full bg-primary/60"
                        :style="`width: ${(role.user_count / maxUsers) * 100}%`"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
