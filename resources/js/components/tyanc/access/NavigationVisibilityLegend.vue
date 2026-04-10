<script setup lang="ts">
import { CheckCircle2, Circle, Lock } from 'lucide-vue-next';
import { useTranslations } from '@/lib/translations';

const { __ } = useTranslations();

const items = [
    {
        icon: CheckCircle2,
        iconClass: 'text-emerald-500',
        label: '__("Granted")',
        description:
            '__("The role has this permission via direct assignment or inheritance.")',
    },
    {
        icon: Circle,
        iconClass: 'text-muted-foreground/30',
        label: '__("Not granted")',
        description: '__("The role does not have this permission.")',
    },
    {
        icon: Lock,
        iconClass: 'text-muted-foreground/40',
        label: '__("Reserved role")',
        description:
            '__("Permissions for this role cannot be changed through the UI.")',
    },
];
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-background/80 p-4"
    >
        <h3 class="mb-3 text-sm font-semibold text-foreground">
            {{ __('Legend') }}
        </h3>

        <ul class="space-y-2.5">
            <li class="flex items-start gap-2.5">
                <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                <div>
                    <p class="text-sm font-medium text-foreground">
                        {{ __('Granted') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            __(
                                'The role has this permission via direct assignment or inheritance.',
                            )
                        }}
                    </p>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <Circle
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground/30"
                />
                <div>
                    <p class="text-sm font-medium text-foreground">
                        {{ __('Not granted') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ __('The role does not have this permission.') }}
                    </p>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <Lock class="mt-0.5 size-4 shrink-0 text-muted-foreground/40" />
                <div>
                    <p class="text-sm font-medium text-foreground">
                        {{ __('Reserved role') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            __(
                                'Permissions for reserved roles cannot be changed through the matrix.',
                            )
                        }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
