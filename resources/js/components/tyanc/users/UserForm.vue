<script setup lang="ts">
import { Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import InputError from '@/components/InputError.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { getInitials } from '@/composables/useInitials';
import { useTranslations } from '@/lib/translations';
import type { RoleOption, SelectOption } from '@/types';

export type UserEditorFields = {
    name: string;
    username: string;
    email: string;
    avatar: File | null;
    remove_avatar: boolean;
    status: string;
    locale: string;
    timezone: string;
    roles: string[];
    permissions: string[];
    first_name: string;
    last_name: string;
    phone_number: string;
    date_of_birth: string;
    gender: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    country: string;
    postal_code: string;
    company_name: string;
    job_title: string;
    bio: string;
    social_links: {
        linkedin: string;
        twitter: string;
        github: string;
    };
    password?: string;
    password_confirmation?: string;
};

const props = withDefaults(
    defineProps<{
        modelValue: UserEditorFields;
        errors: Partial<Record<string, string>>;
        roles: RoleOption[];
        permissions: SelectOption[];
        locales: SelectOption[];
        statuses: SelectOption[];
        timezones: string[];
        currentAvatarUrl?: string | null;
        showPasswordFields?: boolean;
        passwordOptional?: boolean;
    }>(),
    {
        currentAvatarUrl: null,
        showPasswordFields: false,
        passwordOptional: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: UserEditorFields];
}>();

const { __ } = useTranslations();

function update<K extends keyof UserEditorFields>(
    key: K,
    value: UserEditorFields[K],
) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function updateRole(role: string, checked: boolean) {
    const next = checked
        ? [...props.modelValue.roles, role]
        : props.modelValue.roles.filter((r) => r !== role);
    update('roles', next);
}

function updatePermission(permission: string, checked: boolean) {
    const next = checked
        ? [...props.modelValue.permissions, permission]
        : props.modelValue.permissions.filter((p) => p !== permission);
    update('permissions', next);
}

function updateSocialLink(
    key: keyof UserEditorFields['social_links'],
    value: string,
) {
    update('social_links', {
        ...props.modelValue.social_links,
        [key]: value,
    });
}

const avatarInputRef = ref<HTMLInputElement | null>(null);

function openAvatarPicker() {
    avatarInputRef.value?.click();
}

function handleAvatarChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    update('avatar', file);

    if (file !== null) {
        update('remove_avatar', false);
    }
}

const avatarFileName = computed(() => {
    if (props.modelValue.avatar instanceof File) {
        return props.modelValue.avatar.name;
    }
    return null;
});

const avatarPreview = computed(() => {
    if (props.modelValue.avatar instanceof File) {
        return URL.createObjectURL(props.modelValue.avatar);
    }

    if (props.modelValue.remove_avatar) {
        return null;
    }

    return props.currentAvatarUrl;
});

const genderOptions = computed<SelectOption[]>(() => [
    { value: 'male', label: __('Male') },
    { value: 'female', label: __('Female') },
]);
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Account') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-[140px_minmax(0,1fr)]">
            <!-- Avatar column -->
            <div class="space-y-3">
                <Label>{{ __('Avatar') }}</Label>
                <div
                    class="flex flex-col items-center gap-3 rounded-2xl border border-sidebar-border/70 bg-sidebar/20 p-4"
                >
                    <Avatar class="size-20">
                        <AvatarImage
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            :alt="props.modelValue.name || __('User')"
                        />
                        <AvatarFallback>
                            {{
                                getInitials(props.modelValue.name || __('User'))
                            }}
                        </AvatarFallback>
                    </Avatar>
                    <!-- Hidden native file input -->
                    <input
                        ref="avatarInputRef"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="handleAvatarChange"
                    />

                    <!-- Custom upload trigger -->
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        @click="openAvatarPicker"
                    >
                        <Upload class="size-3.5" />
                        {{
                            avatarPreview
                                ? __('Change photo')
                                : __('Upload photo')
                        }}
                    </Button>

                    <!-- Selected filename / fallback -->
                    <p
                        v-if="avatarFileName"
                        class="w-full truncate text-center text-xs text-muted-foreground"
                        :title="avatarFileName"
                    >
                        {{ avatarFileName }}
                    </p>
                    <div
                        v-if="props.currentAvatarUrl"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            id="uf-remove-avatar"
                            :checked="props.modelValue.remove_avatar"
                            @update:checked="
                                update('remove_avatar', Boolean($event))
                            "
                        />
                        <Label
                            for="uf-remove-avatar"
                            class="cursor-pointer text-xs text-muted-foreground"
                        >
                            {{ __('Remove avatar') }}
                        </Label>
                    </div>
                </div>
                <InputError :message="props.errors.avatar" />
            </div>

            <!-- Right-side fields -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="uf-name">{{ __('Full name') }}</Label>
                    <Input
                        id="uf-name"
                        type="text"
                        autocomplete="name"
                        :placeholder="__('Jane Smith')"
                        :model-value="props.modelValue.name"
                        @update:model-value="update('name', String($event))"
                    />
                    <InputError :message="props.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="uf-username">{{ __('Username') }}</Label>
                    <Input
                        id="uf-username"
                        type="text"
                        autocomplete="username"
                        placeholder="janesmith"
                        :model-value="props.modelValue.username"
                        @update:model-value="update('username', String($event))"
                    />
                    <InputError :message="props.errors.username" />
                </div>

                <div class="grid gap-2">
                    <Label for="uf-email">{{ __('Email address') }}</Label>
                    <Input
                        id="uf-email"
                        type="email"
                        autocomplete="email"
                        placeholder="jane@example.com"
                        :model-value="props.modelValue.email"
                        @update:model-value="update('email', String($event))"
                    />
                    <InputError :message="props.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="uf-status">{{ __('Status') }}</Label>
                    <Select
                        :model-value="props.modelValue.status"
                        @update:model-value="update('status', String($event))"
                    >
                        <SelectTrigger id="uf-status" class="w-full">
                            <SelectValue :placeholder="__('Select status')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.statuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ __(option.label) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="props.errors.status" />
                </div>

                <div class="grid gap-2">
                    <Label for="uf-locale">{{ __('Language') }}</Label>
                    <Select
                        :model-value="props.modelValue.locale"
                        @update:model-value="update('locale', String($event))"
                    >
                        <SelectTrigger id="uf-locale" class="w-full">
                            <SelectValue :placeholder="__('Select language')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.locales"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ __(option.label) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="props.errors.locale" />
                </div>

                <div class="grid gap-2">
                    <Label for="uf-timezone">{{ __('Timezone') }}</Label>
                    <TimezoneCombobox
                        id="uf-timezone"
                        :model-value="props.modelValue.timezone"
                        :timezones="props.timezones"
                        @update:model-value="update('timezone', String($event))"
                    />
                    <InputError :message="props.errors.timezone" />
                </div>
            </div>
        </div>

        <!-- Password fields -->
        <div
            v-if="props.showPasswordFields"
            class="grid items-start gap-4 sm:grid-cols-2"
        >
            <div class="grid gap-2">
                <Label for="uf-password">{{ __('Password') }}</Label>
                <Input
                    id="uf-password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="__('Choose a strong password')"
                    :model-value="props.modelValue.password ?? ''"
                    @update:model-value="update('password', String($event))"
                />
                <FormFieldSupport
                    :hint="
                        props.passwordOptional
                            ? __('Leave blank to keep the current password.')
                            : undefined
                    "
                    :error="props.errors.password"
                />
            </div>

            <div class="grid gap-2">
                <Label for="uf-password-confirm">
                    {{ __('Confirm password') }}
                </Label>
                <Input
                    id="uf-password-confirm"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="__('Repeat your password')"
                    :model-value="props.modelValue.password_confirmation ?? ''"
                    @update:model-value="
                        update('password_confirmation', String($event))
                    "
                />
                <FormFieldSupport :error="props.errors.password_confirmation" />
            </div>
        </div>
    </div>

    <Separator />

    <!-- Profile section -->
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Profile') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="uf-first-name">{{ __('First name') }}</Label>
                <Input
                    id="uf-first-name"
                    type="text"
                    autocomplete="given-name"
                    :placeholder="__('Jane')"
                    :model-value="props.modelValue.first_name"
                    @update:model-value="update('first_name', String($event))"
                />
                <InputError :message="props.errors.first_name" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-last-name">{{ __('Last name') }}</Label>
                <Input
                    id="uf-last-name"
                    type="text"
                    autocomplete="family-name"
                    :placeholder="__('Smith')"
                    :model-value="props.modelValue.last_name"
                    @update:model-value="update('last_name', String($event))"
                />
                <InputError :message="props.errors.last_name" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-phone">{{ __('Phone number') }}</Label>
                <Input
                    id="uf-phone"
                    type="tel"
                    autocomplete="tel"
                    placeholder="+1 555 000 0000"
                    :model-value="props.modelValue.phone_number"
                    @update:model-value="update('phone_number', String($event))"
                />
                <InputError :message="props.errors.phone_number" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-dob">{{ __('Date of birth') }}</Label>
                <DatePickerField
                    id="uf-dob"
                    :model-value="props.modelValue.date_of_birth"
                    @update:model-value="update('date_of_birth', $event ?? '')"
                />
                <InputError :message="props.errors.date_of_birth" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-gender">{{ __('Gender') }}</Label>
                <Select
                    :model-value="props.modelValue.gender"
                    @update:model-value="update('gender', String($event))"
                >
                    <SelectTrigger id="uf-gender" class="w-full">
                        <SelectValue :placeholder="__('Select gender')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in genderOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="props.errors.gender" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="uf-bio">{{ __('Bio') }}</Label>
                <Textarea
                    id="uf-bio"
                    :rows="3"
                    :placeholder="__('A short bio…')"
                    :model-value="props.modelValue.bio"
                    @update:model-value="update('bio', String($event))"
                />
                <InputError :message="props.errors.bio" />
            </div>
        </div>
    </div>

    <Separator />

    <!-- Address section -->
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Address') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="uf-address1">{{ __('Address line 1') }}</Label>
                <Input
                    id="uf-address1"
                    type="text"
                    autocomplete="address-line1"
                    :placeholder="__('123 Main St')"
                    :model-value="props.modelValue.address_line_1"
                    @update:model-value="
                        update('address_line_1', String($event))
                    "
                />
                <InputError :message="props.errors.address_line_1" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="uf-address2">{{ __('Address line 2') }}</Label>
                <Input
                    id="uf-address2"
                    type="text"
                    autocomplete="address-line2"
                    :placeholder="__('Apt 4B')"
                    :model-value="props.modelValue.address_line_2"
                    @update:model-value="
                        update('address_line_2', String($event))
                    "
                />
                <InputError :message="props.errors.address_line_2" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-city">{{ __('City') }}</Label>
                <Input
                    id="uf-city"
                    type="text"
                    autocomplete="address-level2"
                    :placeholder="__('New York')"
                    :model-value="props.modelValue.city"
                    @update:model-value="update('city', String($event))"
                />
                <InputError :message="props.errors.city" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-state">{{ __('State / Province') }}</Label>
                <Input
                    id="uf-state"
                    type="text"
                    autocomplete="address-level1"
                    :placeholder="__('NY')"
                    :model-value="props.modelValue.state"
                    @update:model-value="update('state', String($event))"
                />
                <InputError :message="props.errors.state" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-country">{{ __('Country') }}</Label>
                <Input
                    id="uf-country"
                    type="text"
                    autocomplete="country"
                    :placeholder="__('US')"
                    :model-value="props.modelValue.country"
                    @update:model-value="update('country', String($event))"
                />
                <InputError :message="props.errors.country" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-postal">{{ __('Postal code') }}</Label>
                <Input
                    id="uf-postal"
                    type="text"
                    autocomplete="postal-code"
                    placeholder="10001"
                    :model-value="props.modelValue.postal_code"
                    @update:model-value="update('postal_code', String($event))"
                />
                <InputError :message="props.errors.postal_code" />
            </div>
        </div>
    </div>

    <Separator />

    <!-- Work section -->
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Work') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="uf-company">{{ __('Company') }}</Label>
                <Input
                    id="uf-company"
                    type="text"
                    autocomplete="organization"
                    :placeholder="__('Acme Corp')"
                    :model-value="props.modelValue.company_name"
                    @update:model-value="update('company_name', String($event))"
                />
                <InputError :message="props.errors.company_name" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-job-title">{{ __('Job title') }}</Label>
                <Input
                    id="uf-job-title"
                    type="text"
                    autocomplete="organization-title"
                    :placeholder="__('Software Engineer')"
                    :model-value="props.modelValue.job_title"
                    @update:model-value="update('job_title', String($event))"
                />
                <InputError :message="props.errors.job_title" />
            </div>
        </div>
    </div>

    <Separator />

    <!-- Roles section -->
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Roles') }}
        </h3>
        <div class="flex flex-wrap gap-3">
            <label
                v-for="role in props.roles"
                :key="role.value"
                class="flex cursor-pointer items-center gap-2 rounded-full border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
            >
                <Checkbox
                    :checked="props.modelValue.roles.includes(role.value)"
                    @update:checked="updateRole(role.value, Boolean($event))"
                />
                <span>{{ role.label }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ __('Level :level', { level: String(role.level) }) }}
                </span>
            </label>
        </div>
        <InputError :message="props.errors.roles" />
    </div>

    <Separator />

    <!-- Direct permissions section -->
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Direct permissions') }}
        </h3>
        <div class="flex flex-wrap gap-3">
            <label
                v-for="permission in props.permissions"
                :key="permission.value"
                class="flex cursor-pointer items-center gap-2 rounded-full border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
            >
                <Checkbox
                    :checked="
                        props.modelValue.permissions.includes(permission.value)
                    "
                    @update:checked="
                        updatePermission(permission.value, Boolean($event))
                    "
                />
                <span class="font-mono text-xs">{{ permission.label }}</span>
            </label>
        </div>
        <FormFieldSupport
            :hint="
                __(
                    'Permissions are inherited from roles plus any direct assignments.',
                )
            "
            :error="props.errors.permissions"
        />
    </div>

    <Separator />

    <!-- Social links section -->
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-foreground">
            {{ __('Social links') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="uf-linkedin">{{ __('LinkedIn URL') }}</Label>
                <Input
                    id="uf-linkedin"
                    type="url"
                    :model-value="props.modelValue.social_links.linkedin"
                    @update:model-value="
                        updateSocialLink('linkedin', String($event))
                    "
                />
                <InputError :message="props.errors['social_links.linkedin']" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-twitter">{{ __('Twitter URL') }}</Label>
                <Input
                    id="uf-twitter"
                    type="url"
                    :model-value="props.modelValue.social_links.twitter"
                    @update:model-value="
                        updateSocialLink('twitter', String($event))
                    "
                />
                <InputError :message="props.errors['social_links.twitter']" />
            </div>

            <div class="grid gap-2">
                <Label for="uf-github">{{ __('GitHub URL') }}</Label>
                <Input
                    id="uf-github"
                    type="url"
                    :model-value="props.modelValue.social_links.github"
                    @update:model-value="
                        updateSocialLink('github', String($event))
                    "
                />
                <InputError :message="props.errors['social_links.github']" />
            </div>
        </div>
    </div>
</template>
