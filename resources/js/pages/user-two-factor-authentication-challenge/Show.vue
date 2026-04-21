<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SectionState from '@/components/state/SectionState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useTranslations } from '@/lib/translations';
import { store } from '@/routes/login';
import type { TwoFactorConfigContent } from '@/types';

const props = withDefaults(defineProps<{ enabled?: boolean }>(), {
    enabled: true,
});

const { __ } = useTranslations();

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: __('Use a recovery code'),
            description: __(
                'Enter one of your emergency recovery codes to regain access to your account.',
            ),
            buttonText: __('use an authenticator code instead'),
        };
    }

    return {
        title: __('Two-factor verification'),
        description: __(
            'Open your authenticator app and enter the 6-digit code for your account.',
        ),
        buttonText: __('use a recovery code instead'),
    };
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');

const layoutContent = computed<TwoFactorConfigContent>(() => {
    if (!props.enabled) {
        return {
            title: __('Two-factor authentication is disabled'),
            description: __(
                'Two-factor authentication (2FA) is not available on this application. Contact your administrator for more information.',
            ),
            buttonText: '',
        };
    }

    return authConfigContent.value;
});
</script>

<template>
    <AuthLayout
        :title="layoutContent.title"
        :description="layoutContent.description"
    >
        <Head :title="__('Two-factor authentication')" />

        <template v-if="props.enabled">
            <div class="space-y-6">
                <template v-if="!showRecoveryInput">
                    <Form
                        v-bind="store.form()"
                        class="space-y-4"
                        reset-on-error
                        @error="code = ''"
                        #default="{ errors, processing, clearErrors }"
                    >
                        <input type="hidden" name="code" :value="code" />
                        <div
                            class="flex flex-col items-center justify-center space-y-3 text-center"
                        >
                            <div
                                class="flex w-full items-center justify-center"
                            >
                                <InputOTP
                                    id="otp"
                                    v-model="code"
                                    :maxlength="6"
                                    :disabled="processing"
                                    autofocus
                                >
                                    <InputOTPGroup>
                                        <InputOTPSlot
                                            v-for="index in 6"
                                            :key="index"
                                            :index="index - 1"
                                        />
                                    </InputOTPGroup>
                                </InputOTP>
                            </div>
                            <InputError :message="errors.code" />
                        </div>
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="processing"
                            >{{ __('Verify and sign in') }}</Button
                        >
                        <div class="text-center text-sm text-muted-foreground">
                            <button
                                type="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                @click="() => toggleRecoveryMode(clearErrors)"
                            >
                                {{ authConfigContent.buttonText }}
                            </button>
                        </div>
                    </Form>
                </template>

                <template v-else>
                    <Form
                        v-bind="store.form()"
                        class="space-y-4"
                        reset-on-error
                        #default="{ errors, processing, clearErrors }"
                    >
                        <Input
                            name="recovery_code"
                            type="text"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            :autofocus="showRecoveryInput"
                            required
                        />
                        <InputError :message="errors.recovery_code" />
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="processing"
                        >
                            {{ __('Verify and sign in') }}
                        </Button>

                        <div class="text-center text-sm text-muted-foreground">
                            <button
                                type="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                @click="() => toggleRecoveryMode(clearErrors)"
                            >
                                {{ authConfigContent.buttonText }}
                            </button>
                        </div>
                    </Form>
                </template>
            </div>
        </template>

        <template v-else>
            <SectionState
                :icon="ShieldOff"
                :title="__('Two-factor authentication is disabled')"
                :description="
                    __(
                        'Two-factor authentication (2FA) is not available on this application. Contact your administrator for more information.',
                    )
                "
            />
        </template>
    </AuthLayout>
</template>
