<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PasswordController from '@/actions/App/Http/Controllers/UserPasswordController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useTranslations } from '@/lib/translations';
import { edit } from '@/routes/password';

const { settingsBreadcrumbs } = useAppNavigation();
const breadcrumbs = settingsBreadcrumbs('Password', edit());
const { __ } = useTranslations();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="__('Password settings')" />

        <h1 class="sr-only">{{ __('Password settings') }}</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="__('Update password')"
                    :description="
                        __(
                            'Ensure your account is using a long, random password to stay secure',
                        )
                    "
                />

                <Form
                    v-bind="PasswordController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="current_password">{{
                            __('Current password')
                        }}</Label>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            :placeholder="__('Current password')"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">{{ __('New password') }}</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            :placeholder="__('New password')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">{{
                            __('Confirm password')
                        }}</Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            :placeholder="__('Confirm password')"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-password-button"
                        >
                            <Spinner v-if="processing" />
                            {{ __('Save password') }}
                        </Button>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
