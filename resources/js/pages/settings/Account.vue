<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Camera, ShieldAlert } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import AccountSettingsController from '@/actions/App/Http/Controllers/AccountSettingsController';
import DeleteUser from '@/components/DeleteUser.vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BannerState from '@/components/state/BannerState.vue';
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
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useTranslations } from '@/lib/translations';
import { edit } from '@/routes/settings/account';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    canManageStatus: boolean;
    locales: string[];
    timezones: string[];
    statuses?: string[];
};

const props = defineProps<Props>();

const { settingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() => settingsBreadcrumbs('Account', edit()));

const { __ } = useTranslations();
const { getInitials } = useInitials();

const page = usePage();
const user = computed(() => page.props.auth.user!);

/** Avatar */
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

function handleSuccess() {
    revokeAvatarPreview();
    avatarPreview.value = null;
}

onUnmounted(() => {
    revokeAvatarPreview();
});

const currentAvatarSrc = computed(
    () => avatarPreview.value ?? user.value.avatar ?? null,
);

/** Reactive select values */
const selectedLocale = ref<string>(user.value.locale ?? '');
const selectedTimezone = ref<string>(user.value.timezone ?? '');
const selectedStatus = ref<string>(user.value.status ?? '');

watch(
    () => [user.value.locale, user.value.timezone, user.value.status] as const,
    ([locale, timezone, status]) => {
        selectedLocale.value = locale ?? '';
        selectedTimezone.value = timezone ?? '';
        selectedStatus.value = status ?? '';
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="__('Account settings')" />

        <h1 class="sr-only">{{ __('Account settings') }}</h1>

        <SettingsLayout>
            <!-- Reserved-user notice -->
            <BannerState
                v-if="user.is_reserved"
                :icon="ShieldAlert"
                variant="warning"
                :description="
                    __(
                        'This is a reserved system account. Some settings may be restricted.',
                    )
                "
            />

            <Form
                v-bind="AccountSettingsController.update.form()"
                :options="{ preserveScroll: true }"
                class="space-y-10"
                @success="handleSuccess"
                v-slot="{ errors, processing }"
            >
                <!-- ── Avatar ──────────────────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="__('Profile photo')"
                        :description="__('A photo helps people recognise you')"
                    />

                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <Avatar class="size-16">
                                <AvatarImage
                                    v-if="currentAvatarSrc"
                                    :src="currentAvatarSrc"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="text-sm font-medium uppercase"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <button
                                type="button"
                                class="absolute -right-1 -bottom-1 flex size-6 items-center justify-center rounded-full border bg-background shadow-sm transition hover:bg-muted"
                                :aria-label="__('Change profile photo')"
                                @click="openAvatarPicker"
                            >
                                <Camera class="size-3" />
                            </button>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openAvatarPicker"
                        >
                            {{ __('Change photo') }}
                        </Button>

                        <!-- Hidden file input -->
                        <input
                            ref="avatarInputRef"
                            type="file"
                            name="avatar"
                            accept="image/*"
                            class="hidden"
                            @change="handleAvatarChange"
                        />
                    </div>

                    <FormFieldSupport
                        hint="JPG, PNG or WebP · Max 2 MB"
                        :error="errors.avatar"
                    />
                </div>

                <Separator />

                <!-- ── Account information ────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="__('Account information')"
                        :description="
                            __('Your sign-in details and preferences')
                        "
                    />

                    <div class="grid gap-4">
                        <!-- Name -->
                        <div class="grid gap-2">
                            <Label for="name">{{ __('Full name') }}</Label>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                :default-value="user.name"
                                autocomplete="name"
                                :placeholder="__('Jane Smith')"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Username -->
                        <div class="grid gap-2">
                            <Label for="username">{{ __('Username') }}</Label>
                            <Input
                                id="username"
                                type="text"
                                name="username"
                                :default-value="user.username ?? undefined"
                                autocomplete="username"
                                placeholder="yourhandle"
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
                                :default-value="user.email"
                                required
                                autocomplete="email"
                                placeholder="you@example.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Email verification notice -->
                        <div v-if="mustVerifyEmail && !user.email_verified_at">
                            <p class="text-sm text-muted-foreground">
                                {{ __('Your email address is unverified.') }}
                                <Link
                                    :href="send()"
                                    as="button"
                                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                >
                                    {{ __('Resend verification email.') }}
                                </Link>
                            </p>
                        </div>

                        <!-- Locale + Timezone -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="locale">{{ __('Language') }}</Label>
                                <Select v-model="selectedLocale">
                                    <SelectTrigger id="locale" class="w-full">
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
                                <Label for="timezone">
                                    {{ __('Timezone') }}
                                </Label>
                                <TimezoneCombobox
                                    id="timezone"
                                    v-model="selectedTimezone"
                                    name="timezone"
                                    :timezones="props.timezones"
                                />
                                <InputError :message="errors.timezone" />
                            </div>
                        </div>

                        <!-- Status (optional, when allowed) -->
                        <div v-if="props.statuses?.length" class="grid gap-2">
                            <Label for="status">
                                {{ __('Account status') }}
                            </Label>
                            <Select
                                v-model="selectedStatus"
                                :disabled="!canManageStatus"
                            >
                                <SelectTrigger id="status" class="w-full">
                                    <SelectValue
                                        :placeholder="__('Select status')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="s in props.statuses"
                                        :key="s"
                                        :value="s"
                                    >
                                        {{
                                            s.charAt(0).toUpperCase() +
                                            s.slice(1)
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input
                                v-if="canManageStatus"
                                type="hidden"
                                name="status"
                                :value="selectedStatus"
                            />
                            <FormFieldSupport
                                :hint="
                                    !canManageStatus
                                        ? __(
                                              'Account status is managed by administrators.',
                                          )
                                        : undefined
                                "
                                :error="errors.status"
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- ── Save ───────────────────────────────────────── -->
                <div class="flex items-center gap-4">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="update-account-button"
                    >
                        {{ __('Save changes') }}
                    </Button>
                </div>
            </Form>

            <Separator />

            <!-- ── Delete account ─────────────────────────────── -->
            <DeleteUser v-if="!user.is_reserved" />
            <BannerState
                v-else
                variant="warning"
                :description="__('Reserved accounts cannot be deleted.')"
            />
        </SettingsLayout>
    </AppLayout>
</template>
