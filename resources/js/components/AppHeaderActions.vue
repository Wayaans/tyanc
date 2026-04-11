<script setup lang="ts">
import { useFullscreen } from '@vueuse/core';
import { Maximize, Minimize, Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import MessageDropdown from '@/components/admin/MessageDropdown.vue';
import NotificationDropdown from '@/components/admin/NotificationDropdown.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/lib/translations';
import type { Appearance } from '@/types';

type AppearanceOption = {
    value: Appearance;
    label: string;
    icon: typeof Sun;
};

const { appearance, updateAppearance } = useAppearance();
const {
    isFullscreen,
    isSupported: isFullscreenSupported,
    toggle: toggleFullscreen,
} = useFullscreen();
const { __ } = useTranslations();

const appearanceOptions = computed<AppearanceOption[]>(() => [
    {
        value: 'light',
        label: __('Light'),
        icon: Sun,
    },
    {
        value: 'dark',
        label: __('Dark'),
        icon: Moon,
    },
    {
        value: 'system',
        label: __('System'),
        icon: Monitor,
    },
]);

const currentAppearance = computed(
    () =>
        appearanceOptions.value.find(
            (option) => option.value === appearance.value,
        ) ?? appearanceOptions.value[2],
);

const handleAppearanceChange = (value: string) => {
    if (value !== 'light' && value !== 'dark' && value !== 'system') {
        return;
    }

    updateAppearance(value);
};

const handleFullscreenToggle = async () => {
    if (!isFullscreenSupported.value) {
        return;
    }

    try {
        await toggleFullscreen();
    } catch {
        // Ignore fullscreen API rejections caused by browser restrictions.
    }
};
</script>

<template>
    <div class="flex items-center gap-0.5 sm:gap-1">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="size-8">
                    <component :is="currentAppearance.icon" class="size-4" />
                    <span class="sr-only">{{ __('Color mode') }}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-44 rounded-lg">
                <DropdownMenuLabel>{{ __('Color mode') }}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuRadioGroup
                    :model-value="appearance"
                    @update:model-value="handleAppearanceChange"
                >
                    <DropdownMenuRadioItem
                        v-for="option in appearanceOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        <component :is="option.icon" class="size-4" />
                        {{ option.label }}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>

        <NotificationDropdown />

        <MessageDropdown />

        <Button
            variant="ghost"
            size="icon"
            class="size-8"
            :disabled="!isFullscreenSupported"
            @click="void handleFullscreenToggle()"
        >
            <Maximize v-if="!isFullscreen" class="size-4" />
            <Minimize v-else class="size-4" />
            <span class="sr-only">{{ __('Toggle fullscreen') }}</span>
        </Button>
    </div>
</template>
