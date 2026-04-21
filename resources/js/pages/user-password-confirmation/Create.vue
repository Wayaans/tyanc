<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SectionState from '@/components/state/SectionState.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useTranslations } from '@/lib/translations';
import { store } from '@/routes/password/confirm';

withDefaults(defineProps<{ enabled?: boolean }>(), { enabled: true });

const { __ } = useTranslations();
</script>

<template>
    <AuthLayout
        :title="__('Confirm your identity')"
        :description="
            __(
                'This is a protected area. Please re-enter your password to proceed.',
            )
        "
    >
        <Head :title="__('Confirm password')" />

        <template v-if="enabled">
            <Form
                v-bind="store.form()"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="password">{{
                            __('Current password')
                        }}</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="current-password"
                            autofocus
                            :placeholder="__('Your password')"
                        />

                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center">
                        <Button
                            class="w-full"
                            :disabled="processing"
                            data-test="confirm-password-button"
                        >
                            <Spinner v-if="processing" />
                            {{ __('Confirm and continue') }}
                        </Button>
                    </div>
                </div>
            </Form>
        </template>

        <template v-else>
            <SectionState
                :icon="Lock"
                :title="__('Password confirmation is disabled')"
                :description="
                    __(
                        'Password confirmation is not available on this application. Contact your administrator for more information.',
                    )
                "
            />
        </template>
    </AuthLayout>
</template>
