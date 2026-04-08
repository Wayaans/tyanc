<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/tyanc/settings/appearance';
import { edit as editApplication } from '@/routes/tyanc/settings/application';
import { edit as editSecurity } from '@/routes/tyanc/settings/security';
import { edit as editUserDefaults } from '@/routes/tyanc/settings/user-defaults';
import type { NavLinkItem } from '@/types';

const navItems: NavLinkItem[] = [
    { title: 'Application', href: editApplication() },
    { title: 'App Appearance', href: editAppearance() },
    { title: 'Security', href: editSecurity() },
    { title: 'Defaults for New Users', href: editUserDefaults() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="App Settings"
            description="Configure global application settings and defaults"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Admin settings navigation"
                >
                    <Button
                        v-for="item in navItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="toUrl(item.href)">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
