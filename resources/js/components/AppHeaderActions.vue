<script setup lang="ts">
import { useFullscreen } from '@vueuse/core';
import {
    Bell,
    Maximize,
    MessageSquareMore,
    Minimize,
    Monitor,
    Moon,
    Sun,
} from 'lucide-vue-next';
import { computed } from 'vue';
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
import type { Appearance } from '@/types';

type AppearanceOption = {
    value: Appearance;
    label: string;
    icon: typeof Sun;
};

const appearanceOptions: AppearanceOption[] = [
    {
        value: 'light',
        label: 'Light',
        icon: Sun,
    },
    {
        value: 'dark',
        label: 'Dark',
        icon: Moon,
    },
    {
        value: 'system',
        label: 'System',
        icon: Monitor,
    },
];

const { appearance, updateAppearance } = useAppearance();
const {
    isFullscreen,
    isSupported: isFullscreenSupported,
    toggle: toggleFullscreen,
} = useFullscreen();

const currentAppearance = computed(
    () =>
        appearanceOptions.find((option) => option.value === appearance.value) ??
        appearanceOptions[2],
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
    <div class="flex items-center gap-1">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="size-8">
                    <component :is="currentAppearance.icon" class="size-4" />
                    <span class="sr-only">Color mode</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-44 rounded-lg">
                <DropdownMenuLabel>Color mode</DropdownMenuLabel>
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

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="size-8">
                    <Bell class="size-4" />
                    <span class="sr-only">Notifications</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-72 rounded-lg">
                <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div class="px-2 py-4 text-sm text-muted-foreground">
                    You're all caught up.
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="size-8">
                    <MessageSquareMore class="size-4" />
                    <span class="sr-only">Chats</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-72 rounded-lg">
                <DropdownMenuLabel>Chats</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div class="px-2 py-4 text-sm text-muted-foreground">
                    No unread conversations.
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <Button
            variant="ghost"
            size="icon"
            class="size-8"
            :disabled="!isFullscreenSupported"
            @click="void handleFullscreenToggle()"
        >
            <Maximize v-if="!isFullscreen" class="size-4" />
            <Minimize v-else class="size-4" />
            <span class="sr-only">Toggle fullscreen</span>
        </Button>
    </div>
</template>
