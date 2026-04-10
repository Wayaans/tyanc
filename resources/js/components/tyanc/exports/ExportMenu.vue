<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/translations';

export type ExportOption = {
    label: string;
    url: string;
    description?: string;
};

const props = withDefaults(
    defineProps<{
        options: ExportOption[];
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const { __ } = useTranslations();
</script>

<template>
    <Button
        v-if="props.disabled"
        variant="outline"
        size="sm"
        class="gap-2 opacity-60"
        disabled
    >
        <Download class="size-4" />
        {{ __('Export') }}
    </Button>

    <DropdownMenu v-else>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2">
                <Download class="size-4" />
                {{ __('Export') }}
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel
                class="text-xs font-normal text-muted-foreground"
            >
                {{ __('Export menu') }}
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <DropdownMenuGroup>
                <DropdownMenuItem
                    v-for="option in props.options"
                    :key="option.url"
                    as-child
                >
                    <a
                        :href="option.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-col items-start gap-0.5"
                    >
                        <span class="text-sm font-medium">{{
                            option.label
                        }}</span>
                        <span
                            v-if="option.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ option.description }}
                        </span>
                    </a>
                </DropdownMenuItem>
            </DropdownMenuGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
