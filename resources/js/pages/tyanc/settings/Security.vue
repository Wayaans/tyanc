<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SecuritySettingsController from '@/actions/App/Http/Controllers/Tyanc/Settings/SecuritySettingsController';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SettingsFormFooter from '@/components/tyanc/settings/SettingsFormFooter.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import TyancSettingsLayout from '@/layouts/tyanc/settings/Layout.vue';
import { edit } from '@/routes/tyanc/settings/security';

type Settings = {
    enforce_2fa: boolean;
    session_timeout: number;
};

type Props = {
    settings: Settings;
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
    tyancSettingsBreadcrumbs('Security', edit()),
);

const enforce2fa = ref(props.settings.enforce_2fa);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Security settings" />

        <h1 class="sr-only">Security settings</h1>

        <TyancSettingsLayout>
            <Form
                v-bind="SecuritySettingsController.update.form()"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <!-- Authentication -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Authentication"
                        description="Two-factor authentication enforcement for all users"
                    />

                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="enforce_2fa"
                                :checked="enforce2fa"
                                @update:checked="enforce2fa = $event"
                            />
                            <!-- Bridge checkbox to hidden boolean input -->
                            <input
                                type="hidden"
                                name="enforce_2fa"
                                :value="enforce2fa ? '1' : '0'"
                            />
                            <div class="grid gap-1">
                                <Label for="enforce_2fa" class="font-medium">
                                    Require two-factor authentication
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    All users must set up 2FA before accessing
                                    the application. Users without 2FA will be
                                    redirected to the setup screen on login.
                                </p>
                            </div>
                        </div>
                        <InputError :message="errors.enforce_2fa" />
                    </div>
                </div>

                <Separator />

                <!-- Sessions -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Sessions"
                        description="Idle session timeout configuration"
                    />

                    <div class="grid max-w-xs gap-2">
                        <Label for="session_timeout">
                            Session timeout (minutes)
                        </Label>
                        <Input
                            id="session_timeout"
                            type="number"
                            name="session_timeout"
                            :default-value="props.settings.session_timeout"
                            min="5"
                            max="10080"
                            step="5"
                            placeholder="120"
                        />
                        <FormFieldSupport
                            hint="Users are logged out after this many minutes of inactivity. Min 5, max 10080 (1 week)."
                            :error="errors.session_timeout"
                        />
                    </div>
                </div>

                <Separator />

                <SettingsFormFooter
                    :processing="processing"
                    :recently-successful="recentlySuccessful"
                />
            </Form>
        </TyancSettingsLayout>
    </AppLayout>
</template>
