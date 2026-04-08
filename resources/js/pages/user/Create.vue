<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Camera } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    locales: string[];
    timezones: string[];
}>();

const selectedLocale = ref<string>(props.locales[0] ?? 'en');
const selectedTimezone = ref<string>('UTC');

// Avatar
const avatarPreview = ref<string | null>(null);
const avatarInputRef = ref<HTMLInputElement | null>(null);

function openAvatarPicker() {
    avatarInputRef.value?.click();
}

function revokeAvatarPreview() {
    if (avatarPreview.value !== null) {
        URL.revokeObjectURL(avatarPreview.value);
    }
}

function handleAvatarChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        revokeAvatarPreview();
        avatarPreview.value = URL.createObjectURL(file);
    }
}

onUnmounted(() => {
    revokeAvatarPreview();
});
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Enter your details below to create your account"
    >
        <Head title="Register" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <!-- Avatar upload -->
                <div class="flex flex-col items-center gap-2">
                    <div class="relative">
                        <Avatar class="size-16">
                            <AvatarImage
                                v-if="avatarPreview"
                                :src="avatarPreview"
                                alt="Avatar preview"
                            />
                            <AvatarFallback
                                class="text-xs text-muted-foreground"
                            >
                                Photo
                            </AvatarFallback>
                        </Avatar>
                        <button
                            type="button"
                            class="absolute -right-1 -bottom-1 flex size-6 items-center justify-center rounded-full border bg-background shadow-sm transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="openAvatarPicker"
                            aria-label="Upload profile photo"
                        >
                            <Camera class="size-3" />
                        </button>
                    </div>
                    <input
                        ref="avatarInputRef"
                        type="file"
                        name="avatar"
                        accept="image/*"
                        class="hidden"
                        @change="handleAvatarChange"
                    />
                    <FormFieldSupport
                        hint="Profile photo · JPG, PNG or WebP · Max 2 MB"
                        :error="errors.avatar"
                    />
                </div>

                <!-- Name row -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            type="text"
                            name="first_name"
                            :tabindex="1"
                            autocomplete="given-name"
                            placeholder="Jane"
                            autofocus
                        />
                        <InputError :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            type="text"
                            name="last_name"
                            :tabindex="2"
                            autocomplete="family-name"
                            placeholder="Smith"
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>

                <!-- Username -->
                <div class="grid gap-2">
                    <Label for="username">
                        Username
                        <span class="ml-1 text-xs text-muted-foreground"
                            >(optional)</span
                        >
                    </Label>
                    <Input
                        id="username"
                        type="text"
                        name="username"
                        :tabindex="3"
                        autocomplete="username"
                        placeholder="janesmith"
                    />
                    <InputError :message="errors.username" />
                </div>

                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        :tabindex="4"
                        autocomplete="email"
                        placeholder="jane@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        placeholder="Choose a strong password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Confirm password -->
                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        :tabindex="6"
                        autocomplete="new-password"
                        placeholder="Repeat your password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <!-- Locale + Timezone row -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="locale">Language</Label>
                        <Select v-model="selectedLocale">
                            <SelectTrigger
                                id="locale"
                                class="w-full"
                                :tabindex="7"
                            >
                                <SelectValue placeholder="Select language" />
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
                        <Label for="timezone">Timezone</Label>
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
                    :tabindex="9"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="10"
                >
                    Log in
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
