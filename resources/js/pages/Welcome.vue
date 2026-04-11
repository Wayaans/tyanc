<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useBranding } from '@/composables/useBranding';
import { useTranslations } from '@/lib/translations';
import { dashboard, login, register } from '@/routes';

defineProps<{
    canRegister: boolean;
}>();

const { __ } = useTranslations();
const { appName, appLogo } = useBranding();
const page = usePage();

const isAuthenticated = computed(() => !!page.props.auth?.user);
</script>

<template>
    <Head />

    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10"
    >
        <div class="flex w-full max-w-sm flex-col items-center gap-10">
            <Link
                :href="isAuthenticated ? dashboard() : login()"
                class="flex flex-col items-center gap-3"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-sidebar"
                >
                    <img
                        v-if="appLogo"
                        :src="appLogo"
                        :alt="appName"
                        class="size-8 rounded-lg object-contain"
                    />
                    <AppLogoIcon
                        v-else
                        class="size-6 fill-current text-foreground"
                    />
                </div>
                <span
                    class="text-sm font-semibold tracking-tight text-foreground"
                    >{{ appName }}</span
                >
            </Link>

            <div class="space-y-3 text-center">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    {{ __('The admin foundation for modern apps') }}
                </h1>
                <p class="text-sm leading-6 text-muted-foreground">
                    {{
                        __(
                            'Manage users, roles, permissions, and app access across your platform.',
                        )
                    }}
                </p>
            </div>

            <div class="flex w-full flex-col gap-3">
                <template v-if="isAuthenticated">
                    <Button :as="Link" :href="dashboard()" class="w-full">
                        {{ __('Go to dashboard') }}
                    </Button>
                </template>
                <template v-else>
                    <Button :as="Link" :href="login()" class="w-full">
                        {{ __('Log in') }}
                    </Button>
                    <Button
                        v-if="canRegister"
                        :as="Link"
                        :href="register()"
                        variant="outline"
                        class="w-full"
                    >
                        {{ __('Create account') }}
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
