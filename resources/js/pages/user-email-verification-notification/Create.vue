<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Ban, MailCheck } from 'lucide-vue-next';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    status?: string;
    enabled?: boolean;
}>();
</script>

<template>
    <AuthLayout
        title="Verify your email"
        description="Check your inbox and click the link we sent to confirm your address."
    >
        <Head title="Email verification" />

        <!-- Feature disabled notice -->
        <template v-if="enabled === false">
            <Alert>
                <Ban class="size-4" />
                <AlertTitle>Email verification unavailable</AlertTitle>
                <AlertDescription>
                    Email verification is not required on this application. You
                    can continue without verifying your email address.
                </AlertDescription>
            </Alert>

            <div class="mt-6 text-center">
                <TextLink :href="logout()" as="button" class="text-sm">
                    Sign out
                </TextLink>
            </div>
        </template>

        <!-- Active state -->
        <template v-else>
            <div
                v-if="status === 'verification-link-sent'"
                class="mb-4 rounded-md bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400"
            >
                A new verification link has been sent to your email address.
            </div>

            <Form
                v-bind="send.form()"
                class="space-y-6 text-center"
                v-slot="{ processing }"
            >
                <div class="flex flex-col items-center gap-3">
                    <MailCheck class="size-10 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        Didn't receive the email? Check your spam folder or
                        request a new link below.
                    </p>
                </div>

                <Button :disabled="processing" class="w-full">
                    <Spinner v-if="processing" />
                    Resend verification email
                </Button>

                <TextLink
                    :href="logout()"
                    as="button"
                    class="mx-auto block text-sm"
                >
                    Sign out instead
                </TextLink>
            </Form>
        </template>
    </AuthLayout>
</template>
