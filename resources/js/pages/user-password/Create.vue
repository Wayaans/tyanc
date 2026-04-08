<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { update } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
    enabled?: boolean;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <AuthLayout
        title="Reset your password"
        description="Enter and confirm your new password below"
    >
        <Head title="Reset password" />

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
            <Form
                v-bind="update.form()"
                :transform="(data) => ({ ...data, token, email })"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="inputEmail"
                            class="mt-1 block w-full"
                            readonly
                        />
                        <InputError :message="errors.email" class="mt-2" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            class="mt-1 block w-full"
                            autofocus
                            placeholder="Choose a strong password"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">
                            Confirm new password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            class="mt-1 block w-full"
                            placeholder="Repeat your new password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        type="submit"
                        class="mt-4 w-full"
                        :disabled="processing"
                        data-test="reset-password-button"
                    >
                        <Spinner v-if="processing" />
                        Set new password
                    </Button>
                </div>
            </Form>
        </template>
    </AuthLayout>
</template>
