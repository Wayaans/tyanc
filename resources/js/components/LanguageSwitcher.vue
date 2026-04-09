<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/translations';
import { update } from '@/routes/locale';

const { locale, availableLocales, __ } = useTranslations();

/**
 * Map locale codes to display names using the browser's Intl API when available.
 * Falls back to the uppercased code.
 */
function displayName(code: string): string {
    try {
        return (
            new Intl.DisplayNames([code], { type: 'language' }).of(code) ??
            code.toUpperCase()
        );
    } catch {
        return code.toUpperCase();
    }
}

function switchLocale(code: string): void {
    if (code === locale.value) {
        return;
    }

    router.patch(update.url(), { locale: code }, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenu v-if="availableLocales.length > 1">
        <DropdownMenuTrigger
            class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :aria-label="__('Switch language')"
        >
            <Globe class="size-4 shrink-0" />
            <span class="uppercase">{{ locale }}</span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="min-w-36">
            <DropdownMenuRadioGroup
                :model-value="locale"
                @update:model-value="(v) => v && switchLocale(String(v))"
            >
                <DropdownMenuRadioItem
                    v-for="code in availableLocales"
                    :key="code"
                    :value="code"
                >
                    {{ displayName(code) }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
