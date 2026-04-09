<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Ban, ShieldOff } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useTranslations } from '@/lib/translations';
import { disable, enable } from '@/lib/two-factor-routes';
import { show } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const { settingsBreadcrumbs } = useAppNavigation();
const breadcrumbs = settingsBreadcrumbs('Two-Factor Auth', show());

const { __ } = useTranslations();

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="__('Two-Factor Authentication')" />

        <h1 class="sr-only">{{ __('Two-Factor Authentication Settings') }}</h1>

        <SettingsLayout>
            <!-- Feature disabled notice -->
            <div v-if="!canManageTwoFactor" class="space-y-6">
                <Heading
                    variant="small"
                    :title="__('Two-factor authentication')"
                    :description="
                        __('Add an extra layer of security to your account')
                    "
                />

                <Alert>
                    <ShieldOff class="size-4" />
                    <AlertTitle>{{
                        __('Two-factor authentication is disabled')
                    }}</AlertTitle>
                    <AlertDescription>
                        {{
                            __(
                                'Two-factor authentication (2FA) is not available on this application. When enabled, you would be prompted for a secure code during sign-in. Contact your administrator for more information.',
                            )
                        }}
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Active management -->
            <div v-else class="space-y-6">
                <Heading
                    variant="small"
                    :title="__('Two-factor authentication')"
                    :description="
                        __('Manage your two-factor authentication settings')
                    "
                />

                <!-- 2FA not yet enabled -->
                <div
                    v-if="!twoFactorEnabled"
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'When you enable two-factor authentication, you will be prompted for a secure pin during login. Retrieve this pin from any TOTP-compatible app (such as Google Authenticator or Authy) on your phone.',
                            )
                        }}
                    </p>

                    <div>
                        <Button
                            v-if="hasSetupData"
                            @click="showSetupModal = true"
                        >
                            {{ __('Continue setup') }}
                        </Button>
                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="showSetupModal = true"
                            #default="{ processing }"
                        >
                            <Button type="submit" :disabled="processing">
                                {{ __('Enable two-factor auth') }}
                            </Button>
                        </Form>
                    </div>
                </div>

                <!-- 2FA already enabled -->
                <div
                    v-else
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                "Two-factor authentication is active. You'll be prompted for a secure code from your authenticator app each time you sign in.",
                            )
                        }}
                    </p>

                    <div class="relative inline">
                        <Form v-bind="disable.form()" #default="{ processing }">
                            <Button
                                variant="destructive"
                                type="submit"
                                :disabled="processing"
                            >
                                {{ __('Disable two-factor auth') }}
                            </Button>
                        </Form>
                    </div>

                    <TwoFactorRecoveryCodes />
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
