<script setup lang="ts">
import { CheckCircle2, Circle } from 'lucide-vue-next';
import { useTranslations } from '@/lib/translations';

const { __ } = useTranslations();
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
        </ul>
    </div>
</template>
