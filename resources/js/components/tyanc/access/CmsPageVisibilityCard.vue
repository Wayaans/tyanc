<script setup lang="ts">
import { Eye, Info, Lock, ShieldCheck } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { RoleData } from '@/types';

const props = defineProps<{
    roles: RoleData[];
}>();

const { __ } = useTranslations();
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-background/80 p-4"
    >
        <div class="mb-3 flex items-center gap-2">
            <Info class="size-4 text-muted-foreground" />
            <h3 class="text-sm font-semibold text-foreground">
                {{ __('CMS page visibility') }}
            </h3>
        </div>

        <p class="mb-3 text-xs text-muted-foreground">
            {{
                __(
                    'Restricted pages should stay governed through Tyanc roles and permissions.',
                )
            }}
        </p>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-lg border border-sidebar-border/50 px-3 py-3">
                <div
                    class="flex items-center gap-2 text-sm font-medium text-foreground"
                >
                    <Eye class="size-4 text-muted-foreground" />
                    {{ __('Public') }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ __('Visible to everyone, including guests.') }}
                </p>
            </div>

            <div class="rounded-lg border border-sidebar-border/50 px-3 py-3">
                <div
                    class="flex items-center gap-2 text-sm font-medium text-foreground"
                >
                    <ShieldCheck class="size-4 text-muted-foreground" />
                    {{ __('Authenticated') }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ __('Visible after sign-in without extra page rules.') }}
                </p>
            </div>

            <div class="rounded-lg border border-sidebar-border/50 px-3 py-3">
                <div
                    class="flex items-center gap-2 text-sm font-medium text-foreground"
                >
                    <Lock class="size-4 text-muted-foreground" />
                    {{ __('Restricted') }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        __(
                            'Visible only when a role or permission grants access.',
                        )
                    }}
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Badge
                v-for="role in props.roles"
                :key="role.id"
                variant="outline"
                class="rounded-full text-xs"
            >
                {{ role.name }}
            </Badge>
        </div>
    </div>
</template>
