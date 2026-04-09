<script setup lang="ts">
import { ChevronsUpDown } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useBranding } from '@/composables/useBranding';
import { useTranslations } from '@/lib/translations';

const { activeApp, activeAppId, apps, switchApp } = useAppNavigation();
const { appLogo, appName } = useBranding();
const { isMobile, state } = useSidebar();
const { __ } = useTranslations();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-sidebar-accent text-sidebar-accent-foreground"
                        >
                            <img
                                v-if="activeApp.id === 'tyanc' && appLogo"
                                :src="appLogo"
                                :alt="appName"
                                class="size-5 rounded-sm object-contain"
                            />
                            <component
                                v-else
                                :is="activeApp.icon"
                                class="size-4 fill-current"
                            />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">
                                {{
                                    activeApp.id === 'tyanc'
                                        ? appName
                                        : activeApp.title
                                }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ activeApp.subtitle }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'right'
                              : 'bottom'
                    "
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        {{ __('Apps') }}
                    </DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        :model-value="activeAppId"
                        @update:model-value="switchApp"
                    >
                        <DropdownMenuRadioItem
                            v-for="app in apps"
                            :key="app.id"
                            :value="app.id"
                            class="gap-3 p-2"
                        >
                            <div
                                class="flex size-7 items-center justify-center overflow-hidden rounded-md bg-muted text-muted-foreground"
                            >
                                <img
                                    v-if="app.id === 'tyanc' && appLogo"
                                    :src="appLogo"
                                    :alt="appName"
                                    class="size-4 rounded-sm object-contain"
                                />
                                <component
                                    v-else
                                    :is="app.icon"
                                    class="size-3.5 fill-current"
                                />
                            </div>
                            <div
                                class="grid flex-1 text-left text-sm leading-tight"
                            >
                                <span class="truncate font-medium">
                                    {{
                                        app.id === 'tyanc' ? appName : app.title
                                    }}
                                </span>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ app.subtitle }}
                                </span>
                            </div>
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
