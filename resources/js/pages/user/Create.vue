<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { useTranslations } from '@/lib/translations';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    locales: string[];
    timezones: string[];
}>();

const { __ } = useTranslations();

const selectedLocale = ref<string>(props.locales[0] ?? 'en');
const selectedTimezone = ref<string>('UTC');
</script>

<template>
    <AuthBase
        :title="__('Create an account')"
        :description="__('Enter your details below to create your account')"
    >
        <Head :title="__('Register')" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <!-- Full name -->
                <div class="grid gap-2">
                    <Label for="name">{{ __('Full name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        name="name"
                        required
                        :tabindex="1"
                        autocomplete="name"
                        :placeholder="__('Jane Smith')"
                        autofocus
                    />
                    <InputError :message="errors.name" />
                </div>

                <!-- Username -->
                <div class="grid gap-2">
                    <Label for="username">
                        {{ __('Username') }}
                        <span class="ml-1 text-xs text-muted-foreground"
                            >({{ __('optional') }})</span
                        >
                    </Label>
                    <Input
                        id="username"
                        type="text"
                        name="username"
                        :tabindex="2"
                        autocomplete="username"
                        placeholder="janesmith"
                    />
                    <InputError :message="errors.username" />
                </div>

                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">{{ __('Email address') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        :tabindex="3"
                        autocomplete="email"
                        placeholder="jane@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <Label for="password">{{ __('Password') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        :placeholder="__('Choose a strong password')"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Confirm password -->
                <div class="grid gap-2">
                    <Label for="password_confirmation">{{
                        __('Confirm password')
                    }}</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        :placeholder="__('Repeat your password')"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <!-- Locale + Timezone row -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="locale">{{ __('Language') }}</Label>
                        <Select v-model="selectedLocale">
                            <SelectTrigger
                                id="locale"
                                class="w-full"
                                :tabindex="6"
                            >
                                <SelectValue
                                    :placeholder="__('Select language')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="locale in props.locales"
                                    :key="locale"
                                    :value="locale"
                                >
                                    {{ locale.toUpperCase() }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="locale"
                            :value="selectedLocale"
                        />
                        <InputError :message="errors.locale" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">{{ __('Timezone') }}</Label>
                        <TimezoneCombobox
                            id="timezone"
                            v-model="selectedTimezone"
                            name="timezone"
                            :timezones="props.timezones"
                        />
                        <InputError :message="errors.timezone" />
                    </div>
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :tabindex="7"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ __('Create account') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ __('Already have an account?') }}
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="8"
                >
                    {{ __('Log in') }}
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
