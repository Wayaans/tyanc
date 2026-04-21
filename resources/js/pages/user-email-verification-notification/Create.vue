<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Ban, MailCheck } from 'lucide-vue-next';
import SectionState from '@/components/state/SectionState.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useTranslations } from '@/lib/translations';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    enabled?: boolean;
}>();

const { __ } = useTranslations();
</script>

<template>
    <AuthLayout
        :title="__('Verify your email')"
        :description="
            __(
                'Check your inbox and click the link we sent to confirm your address.',
            )
        "
    >
        <Head :title="__('Email verification')" />

        <!-- Feature disabled notice -->
        <template v-if="enabled === false">
            <SectionState
                :icon="Ban"
                :title="__('Email verification unavailable')"
                :description="
                    __(
                        'Email verification is not required on this application. You can continue without verifying your email address.',
                    )
                "
            >
                <template #actions>
                    <TextLink :href="logout()" as="button" class="text-sm">
                        {{ __('Sign out') }}
                    </TextLink>
                </template>
            </SectionState>
        </template>

        <!-- Active state -->
        <template v-else>
            <Form
                v-bind="send.form()"
                class="space-y-6 text-center"
                v-slot="{ processing }"
            >
                <div class="flex flex-col items-center gap-3">
                    <MailCheck class="size-10 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                "Didn't receive the email? Check your spam folder or request a new link below.",
                            )
                        }}
                    </p>
                </div>

                <Button :disabled="processing" class="w-full">
                    <Spinner v-if="processing" />
                    {{ __('Resend verification email') }}
                </Button>

                <TextLink
                    :href="logout()"
                    as="button"
                    class="mx-auto block text-sm"
                >
                    {{ __('Sign out instead') }}
                </TextLink>
            </Form>
        </template>
    </AuthLayout>
</template>
