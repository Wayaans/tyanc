<script setup lang="ts">
import { ChevronDown, ChevronUp, Info, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import InputError from '@/components/InputError.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import type { PermissionOption, RoleOption, SelectOption } from '@/types';

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
        permissions: PermissionOption[];
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

/** Toggle for the direct permissions section (collapsed by default). */
const showDirectPermissions = ref(props.modelValue.permissions.length > 0);

watch(
    () => props.modelValue.permissions,
    (permissions) => {
        showDirectPermissions.value = permissions.length > 0;
    },
    { deep: true },
);

/**
 * Lightweight effective-access hint:
 * union of all permissions from selected roles + direct assignments.
 * Excludes duplicates. Capped at display limit for brevity.
 */
const effectivePermissions = computed<string[]>(() => {
    const set = new Set<string>();

    for (const roleValue of props.modelValue.roles) {
        const role = props.roles.find((r) => r.value === roleValue);
        if (role) {
            for (const perm of role.permissions) {
                set.add(perm);
            }
        }
    }

    for (const perm of props.modelValue.permissions) {
        set.add(perm);
    }

    return Array.from(set).sort();
});

const effectivePermissionsPreview = computed(() =>
    effectivePermissions.value.slice(0, 6),
);
const effectivePermissionsOverflow = computed(
    () =>
        effectivePermissions.value.length -
        effectivePermissionsPreview.value.length,
);
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

    <!-- Roles section (primary) -->
    <div class="space-y-4">
        <div class="flex items-start justify-between gap-2">
            <div class="space-y-0.5">
                <h3 class="text-sm font-semibold text-foreground">
                    {{ __('Roles') }}
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{
                        __(
                            'Access is primarily granted through roles. Assign the right role rather than individual permissions.',
                        )
                    }}
                </p>
            </div>
            <Badge
                v-if="props.modelValue.roles.length > 0"
                variant="secondary"
                class="shrink-0 rounded-full text-xs tabular-nums"
            >
                {{ props.modelValue.roles.length }}
            </Badge>
        </div>

        <div class="flex flex-wrap gap-3">
            <div
                v-for="role in props.roles"
                :key="role.value"
                class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm transition-colors hover:bg-muted/30"
                :class="{
                    'border-primary/40 bg-primary/5':
                        props.modelValue.roles.includes(role.value),
                }"
            >
                <Checkbox
                    :id="`uf-role-${role.value}`"
                    class="mt-0.5"
                    :checked="props.modelValue.roles.includes(role.value)"
                    @update:checked="updateRole(role.value, Boolean($event))"
                />
                <Label
                    :for="`uf-role-${role.value}`"
                    class="cursor-pointer space-y-0.5"
                >
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium">{{ role.label }}</span>
                        <Badge
                            v-if="role.is_reserved"
                            variant="outline"
                            class="rounded-full px-1.5 py-0 text-xs"
                        >
                            {{ __('Reserved') }}
                        </Badge>
                    </div>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span>
                            {{
                                __('Level :level', {
                                    level: String(role.level),
                                })
                            }}
                        </span>
                        <span>·</span>
                        <span>
                            {{
                                __(':n permission(s)', {
                                    n: String(role.permission_count),
                                })
                            }}
                        </span>
                    </div>
                </Label>
            </div>
        </div>
        <InputError :message="props.errors.roles" />

        <!-- Effective access hint -->
        <div
            v-if="effectivePermissions.length > 0"
            class="space-y-1.5 rounded-lg border border-sidebar-border/60 bg-muted/20 px-3 py-2.5"
        >
            <div
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Info class="size-3 shrink-0" />
                <span class="font-medium">
                    {{
                        __('Effective access: :n permission(s) total', {
                            n: String(effectivePermissions.length),
                        })
                    }}
                </span>
            </div>
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="perm in effectivePermissionsPreview"
                    :key="perm"
                    class="rounded border border-sidebar-border/50 bg-background px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                >
                    {{ perm }}
                </span>
                <span
                    v-if="effectivePermissionsOverflow > 0"
                    class="rounded bg-muted/30 px-1.5 py-0.5 text-xs text-muted-foreground"
                >
                    +{{ effectivePermissionsOverflow }} {{ __('more') }}
                </span>
            </div>
        </div>
    </div>

    <Separator />

    <!-- Direct permissions section (exception-only, collapsible) -->
    <div class="space-y-3">
        <button
            type="button"
            class="flex w-full items-center justify-between text-left"
            @click="showDirectPermissions = !showDirectPermissions"
        >
            <div class="space-y-0.5">
                <h3
                    class="flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    {{ __('Direct permissions') }}
                    <Badge
                        v-if="props.modelValue.permissions.length > 0"
                        variant="secondary"
                        class="rounded-full text-xs tabular-nums"
                    >
                        {{ props.modelValue.permissions.length }}
                    </Badge>
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{
                        __(
                            'Exception-only. Prefer roles for regular access control.',
                        )
                    }}
                </p>
            </div>
            <ChevronDown
                v-if="!showDirectPermissions"
                class="size-4 shrink-0 text-muted-foreground"
            />
            <ChevronUp v-else class="size-4 shrink-0 text-muted-foreground" />
        </button>

        <template v-if="showDirectPermissions">
            <div
                class="rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"
            >
                {{
                    __(
                        'Direct permissions bypass role hierarchy. Only use these for exceptional one-off grants.',
                    )
                }}
            </div>

            <div class="flex flex-wrap gap-2">
                <div
                    v-for="permission in props.permissions"
                    :key="permission.value"
                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm transition-colors hover:bg-muted/30"
                    :class="{
                        'border-primary/40 bg-primary/5':
                            props.modelValue.permissions.includes(
                                permission.value,
                            ),
                    }"
                >
                    <Checkbox
                        :id="`uf-perm-${permission.value}`"
                        :checked="
                            props.modelValue.permissions.includes(
                                permission.value,
                            )
                        "
                        @update:checked="
                            updatePermission(permission.value, Boolean($event))
                        "
                    />
                    <Label
                        :for="`uf-perm-${permission.value}`"
                        class="cursor-pointer"
                    >
                        <span class="font-mono text-xs">{{
                            permission.label
                        }}</span>
                        <div
                            v-if="permission.app"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ permission.app }}
                            <template v-if="permission.resource">
                                &rsaquo; {{ permission.resource }}
                            </template>
                            <template v-if="permission.action">
                                &rsaquo; {{ permission.action }}
                            </template>
                        </div>
                    </Label>
                </div>
            </div>

            <FormFieldSupport :error="props.errors.permissions" />
        </template>
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
