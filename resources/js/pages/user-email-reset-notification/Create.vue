<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import SectionState from '@/components/state/SectionState.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useTranslations } from '@/lib/translations';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    enabled?: boolean;
}>();

const { __ } = useTranslations();
</script>

<template>
    <AuthLayout
        :title="__('Forgot your password?')"
        :description="
            __('Enter your email address and we\'ll send you a reset link')
        "
    >
        <Head :title="__('Forgot password')" />

        <!-- Feature disabled notice -->
        <template v-if="enabled === false">
            <SectionState
                :icon="Ban"
                variant="warning"
                :title="__('Password reset unavailable')"
                :description="
                    __(
                        'Password reset is not enabled on this application. Please contact support if you need help accessing your account.',
                    )
                "
            >
                <template #actions>
                    <TextLink :href="login()">{{
                        __('Back to sign in')
                    }}</TextLink>
                </template>
            </SectionState>
        </template>

        <!-- Active state -->
        <template v-else>
            <div class="space-y-6">
                <Form v-bind="email.form()" v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="email">{{ __('Email address') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            autofocus
                            placeholder="email@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="my-6 flex items-center justify-start">
                        <Button
                            class="w-full"
                            :disabled="processing"
                            data-test="email-password-reset-link-button"
                        >
                            <Spinner v-if="processing" />
                            {{ __('Send reset link') }}
                        </Button>
                    </div>
                </Form>

                <div
                    class="space-x-1 text-center text-sm text-muted-foreground"
                >
                    <span>{{ __('Remembered your password?') }}</span>
                    <TextLink :href="login()">{{ __('Sign in') }}</TextLink>
                </div>
            </div>
        </template>
    </AuthLayout>
</template>
