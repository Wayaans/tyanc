<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    KeyRound,
    LogOut,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
} from 'lucide-vue-next';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useBranding } from '@/composables/useBranding';
import { logout } from '@/routes';
import { edit as editPassword } from '@/routes/password';
import { edit as editPreferences } from '@/routes/settings/preferences';
import { show as showTwoFactor } from '@/routes/two-factor';
import { edit as editProfile } from '@/routes/user-profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { appName } = useBranding();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="space-y-1 px-1 py-1.5 text-left text-sm">
            <div class="flex items-center gap-2">
                <UserInfo :user="user" :show-email="true" />
            </div>
            <p class="px-1 text-xs text-muted-foreground">
                Signed in to {{ appName }}
            </p>
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />

    <!-- Account settings group -->
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="editProfile()"
                prefetch
            >
                <Settings class="mr-2 h-4 w-4" />
                Profile
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="editPassword()"
                prefetch
            >
                <KeyRound class="mr-2 h-4 w-4" />
                Password
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="showTwoFactor()"
                prefetch
            >
                <ShieldCheck class="mr-2 h-4 w-4" />
                Two-Factor Auth
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />

    <!-- Preferences -->
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="editPreferences()"
                prefetch
            >
                <SlidersHorizontal class="mr-2 h-4 w-4" />
                Preferences
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
