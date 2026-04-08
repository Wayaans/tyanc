<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
    enabled?: boolean;
}>();
</script>

<template>
    <AuthLayout
        title="Forgot your password?"
        description="Enter your email address and we'll send you a reset link"
    >
        <Head title="Forgot password" />

        <!-- Feature disabled notice -->
        <template v-if="enabled === false">
            <Alert>
                <Ban class="size-4" />
                <AlertTitle>Password reset unavailable</AlertTitle>
                <AlertDescription>
                    Password reset is not enabled on this application. Please
                    contact support if you need help accessing your account.
                </AlertDescription>
            </Alert>

            <div class="mt-6 text-center text-sm text-muted-foreground">
                <TextLink :href="login()">Back to sign in</TextLink>
            </div>
        </template>

        <!-- Active state -->
        <template v-else>
            <div
                v-if="status"
                class="mb-4 rounded-md bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400"
            >
                {{ status }}
            </div>

            <div class="space-y-6">
                <Form v-bind="email.form()" v-slot="{ errors, processing }">
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
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
                            Send reset link
                        </Button>
                    </div>
                </Form>

                <div
                    class="space-x-1 text-center text-sm text-muted-foreground"
                >
                    <span>Remembered your password?</span>
                    <TextLink :href="login()">Sign in</TextLink>
                </div>
            </div>
        </template>
    </AuthLayout>
</template>
