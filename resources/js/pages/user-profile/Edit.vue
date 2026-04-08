<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Camera, Github, Linkedin, Twitter } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import DatePickerField from '@/components/DatePickerField.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/user-profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    canManageStatus: boolean;
    locales: string[];
    timezones: string[];
    statuses?: string[];
};

const props = defineProps<Props>();

const { settingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() => settingsBreadcrumbs('Profile', edit()));

const page = usePage();
const user = computed(() => page.props.auth.user!);
const profile = computed(() => user.value.profile);

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

    router.reload({
        only: ['auth', 'theme', 'userPreferences'],
        preserveScroll: true,
    });
}

onUnmounted(() => {
    revokeAvatarPreview();
});

const currentAvatarSrc = computed(
    () => avatarPreview.value ?? user.value.avatar ?? null,
);

const userInitials = computed(() => {
    const first = profile.value?.first_name ?? user.value.name ?? '';
    const last = profile.value?.last_name ?? '';
    return (first[0] ?? '') + (last[0] ?? '');
});

/** Locale / Timezone / Status / Gender / DOB reactive values */
const selectedLocale = ref<string>(user.value.locale ?? '');
const selectedTimezone = ref<string>(user.value.timezone ?? '');
const selectedStatus = ref<string>(user.value.status ?? '');
const selectedGender = ref<string>(profile.value?.gender ?? '');
const dateOfBirth = ref<string | null>(profile.value?.date_of_birth ?? null);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile settings</h1>

        <SettingsLayout>
            <Form
                v-bind="ProfileController.update.form()"
                :options="{ preserveScroll: true }"
                class="space-y-10"
                @success="handleSuccess"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <!-- ── Avatar ──────────────────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Profile photo"
                        description="A photo helps people recognize you"
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
                                    {{ userInitials || '?' }}
                                </AvatarFallback>
                            </Avatar>
                            <button
                                type="button"
                                class="absolute -right-1 -bottom-1 flex size-6 items-center justify-center rounded-full border bg-background shadow-sm transition hover:bg-muted"
                                @click="openAvatarPicker"
                                aria-label="Change profile photo"
                            >
                                <Camera class="size-3" />
                            </button>
                        </div>

                        <div class="flex flex-col gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openAvatarPicker"
                            >
                                Change photo
                            </Button>
                        </div>

                        <!-- Hidden file input (name="avatar" feeds FormData) -->
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

                <!-- ── Account info ────────────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Account information"
                        description="Your sign-in details and preferences"
                    />

                    <div class="grid gap-4">
                        <!-- Username -->
                        <div class="grid gap-2">
                            <Label for="username">Username</Label>
                            <Input
                                id="username"
                                type="text"
                                name="username"
                                :default-value="user.username"
                                autocomplete="username"
                                placeholder="yourhandle"
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
                                Your email address is unverified.
                                <Link
                                    :href="send()"
                                    as="button"
                                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                >
                                    Resend verification email.
                                </Link>
                            </p>
                            <div
                                v-if="status === 'verification-link-sent'"
                                class="mt-2 text-sm font-medium text-green-600"
                            >
                                A new verification link has been sent to your
                                email address.
                            </div>
                        </div>

                        <!-- Locale + Timezone -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="locale">Language</Label>
                                <Select v-model="selectedLocale">
                                    <SelectTrigger id="locale" class="w-full">
                                        <SelectValue
                                            placeholder="Select language"
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

                        <!-- Status -->
                        <div v-if="props.statuses?.length" class="grid gap-2">
                            <Label for="status">Account status</Label>
                            <Select
                                v-model="selectedStatus"
                                :disabled="!canManageStatus"
                            >
                                <SelectTrigger id="status" class="w-full">
                                    <SelectValue placeholder="Select status" />
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
                                        ? 'Account status is managed by administrators.'
                                        : undefined
                                "
                                :error="errors.status"
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- ── Personal details ───────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Personal details"
                        description="Your name, contact, and personal information"
                    />

                    <div class="grid gap-4">
                        <!-- First + Last name -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    type="text"
                                    name="first_name"
                                    :default-value="
                                        profile?.first_name ?? undefined
                                    "
                                    autocomplete="given-name"
                                    placeholder="Jane"
                                />
                                <InputError :message="errors.first_name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    type="text"
                                    name="last_name"
                                    :default-value="
                                        profile?.last_name ?? undefined
                                    "
                                    autocomplete="family-name"
                                    placeholder="Smith"
                                />
                                <InputError :message="errors.last_name" />
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="grid gap-2">
                            <Label for="phone_number">Phone number</Label>
                            <Input
                                id="phone_number"
                                type="tel"
                                name="phone_number"
                                :default-value="
                                    profile?.phone_number ?? undefined
                                "
                                autocomplete="tel"
                                placeholder="+1 555 000 0000"
                            />
                            <InputError :message="errors.phone_number" />
                        </div>

                        <!-- Date of birth + Gender -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="date_of_birth">Date of birth</Label>
                                <DatePickerField
                                    id="date_of_birth"
                                    v-model="dateOfBirth"
                                    name="date_of_birth"
                                />
                                <InputError :message="errors.date_of_birth" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="gender">Gender</Label>
                                <Select v-model="selectedGender">
                                    <SelectTrigger id="gender" class="w-full">
                                        <SelectValue
                                            placeholder="Select gender"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male">
                                            Male
                                        </SelectItem>
                                        <SelectItem value="female">
                                            Female
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="gender"
                                    :value="selectedGender"
                                />
                                <InputError :message="errors.gender" />
                            </div>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- ── Address ─────────────────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Address"
                        description="Your mailing or home address"
                    />

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="address_line_1">Address line 1</Label>
                            <Input
                                id="address_line_1"
                                type="text"
                                name="address_line_1"
                                :default-value="
                                    profile?.address_line_1 ?? undefined
                                "
                                autocomplete="address-line1"
                                placeholder="123 Main St"
                            />
                            <InputError :message="errors.address_line_1" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address_line_2">
                                Address line 2
                                <span class="ml-1 text-xs text-muted-foreground"
                                    >(optional)</span
                                >
                            </Label>
                            <Input
                                id="address_line_2"
                                type="text"
                                name="address_line_2"
                                :default-value="
                                    profile?.address_line_2 ?? undefined
                                "
                                autocomplete="address-line2"
                                placeholder="Apt 4B"
                            />
                            <InputError :message="errors.address_line_2" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="city">City</Label>
                                <Input
                                    id="city"
                                    type="text"
                                    name="city"
                                    :default-value="profile?.city ?? undefined"
                                    autocomplete="address-level2"
                                    placeholder="New York"
                                />
                                <InputError :message="errors.city" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="state">State / Province</Label>
                                <Input
                                    id="state"
                                    type="text"
                                    name="state"
                                    :default-value="profile?.state ?? undefined"
                                    autocomplete="address-level1"
                                    placeholder="NY"
                                />
                                <InputError :message="errors.state" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="postal_code">Postal code</Label>
                                <Input
                                    id="postal_code"
                                    type="text"
                                    name="postal_code"
                                    :default-value="
                                        profile?.postal_code ?? undefined
                                    "
                                    autocomplete="postal-code"
                                    placeholder="10001"
                                />
                                <FormFieldSupport :error="errors.postal_code" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="country">Country</Label>
                                <Input
                                    id="country"
                                    type="text"
                                    name="country"
                                    :default-value="
                                        profile?.country ?? undefined
                                    "
                                    autocomplete="country"
                                    placeholder="US"
                                    maxlength="2"
                                />
                                <FormFieldSupport
                                    hint="2-letter ISO code (e.g. US, GB)"
                                    :error="errors.country"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- ── Professional ───────────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Professional"
                        description="Your work and public profile information"
                    />

                    <div class="grid gap-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="company_name">Company</Label>
                                <Input
                                    id="company_name"
                                    type="text"
                                    name="company_name"
                                    :default-value="
                                        profile?.company_name ?? undefined
                                    "
                                    autocomplete="organization"
                                    placeholder="Acme Corp"
                                />
                                <InputError :message="errors.company_name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="job_title">Job title</Label>
                                <Input
                                    id="job_title"
                                    type="text"
                                    name="job_title"
                                    :default-value="
                                        profile?.job_title ?? undefined
                                    "
                                    autocomplete="organization-title"
                                    placeholder="Software Engineer"
                                />
                                <InputError :message="errors.job_title" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="bio">Bio</Label>
                            <textarea
                                id="bio"
                                name="bio"
                                rows="4"
                                :value="profile?.bio ?? undefined"
                                placeholder="Tell us a little about yourself…"
                                class="w-full min-w-0 resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                            />
                            <InputError :message="errors.bio" />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- ── Social links ────────────────────────────────── -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Social links"
                        description="Connect your public profiles"
                    />

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label
                                for="social_linkedin"
                                class="flex items-center gap-2"
                            >
                                <Linkedin class="size-4" />
                                LinkedIn
                            </Label>
                            <Input
                                id="social_linkedin"
                                type="url"
                                name="social_links[linkedin]"
                                :default-value="
                                    profile?.social_links?.linkedin ?? undefined
                                "
                                placeholder="https://linkedin.com/in/yourprofile"
                            />
                            <InputError
                                :message="errors['social_links.linkedin']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="social_twitter"
                                class="flex items-center gap-2"
                            >
                                <Twitter class="size-4" />
                                Twitter / X
                            </Label>
                            <Input
                                id="social_twitter"
                                type="url"
                                name="social_links[twitter]"
                                :default-value="
                                    profile?.social_links?.twitter ?? undefined
                                "
                                placeholder="https://twitter.com/yourhandle"
                            />
                            <InputError
                                :message="errors['social_links.twitter']"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="social_github"
                                class="flex items-center gap-2"
                            >
                                <Github class="size-4" />
                                GitHub
                            </Label>
                            <Input
                                id="social_github"
                                type="url"
                                name="social_links[github]"
                                :default-value="
                                    profile?.social_links?.github ?? undefined
                                "
                                placeholder="https://github.com/yourhandle"
                            />
                            <InputError
                                :message="errors['social_links.github']"
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
                        data-test="update-profile-button"
                    >
                        Save changes
                    </Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-neutral-600"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </Form>

            <Separator />

            <!-- ── Delete account ─────────────────────────────── -->
            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
