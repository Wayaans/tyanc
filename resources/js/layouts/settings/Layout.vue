<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editPassword } from '@/routes/password';
import { edit as editPreferences } from '@/routes/settings/preferences';
import { show as showTwoFactorAuth } from '@/routes/two-factor';
import { edit as editProfile } from '@/routes/user-profile';
import type { NavLinkItem } from '@/types';

const sidebarNavItems: NavLinkItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Two-Factor Auth',
        href: showTwoFactorAuth(),
    },
    {
        title: 'Preferences',
        href: editPreferences(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();

const activeTab = (): string => {
    for (const item of sidebarNavItems) {
        if (isCurrentOrParentUrl(item.href)) {
            return toUrl(item.href);
        }
    }
    return toUrl(sidebarNavItems[0].href);
};
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Account"
            description="Manage your profile and account settings"
        />

        <!-- Top tabs navigation -->
        <Tabs :model-value="activeTab()" class="mt-6">
            <TabsList class="mb-6 w-full justify-start overflow-x-auto">
                <TabsTrigger
                    v-for="item in sidebarNavItems"
                    :key="toUrl(item.href)"
                    :value="toUrl(item.href)"
                    as-child
                >
                    <Link :href="toUrl(item.href)">
                        {{ item.title }}
                    </Link>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <!-- Page content -->
        <div class="max-w-2xl">
            <section class="space-y-12">
                <slot />
            </section>
        </div>
    </div>
</template>
