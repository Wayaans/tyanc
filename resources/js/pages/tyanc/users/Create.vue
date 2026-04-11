<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import UserForm, {
    type UserEditorFields,
} from '@/components/tyanc/users/UserForm.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index, store } from '@/routes/tyanc/users';
import type { PermissionOption, RoleOption, SelectOption } from '@/types';

const props = defineProps<{
    roles: RoleOption[];
    permissions: PermissionOption[];
    locales: SelectOption[];
    statuses: SelectOption[];
    timezones: string[];
}>();

const { __ } = useTranslations();
const { usersCreateBreadcrumbs } = useAppNavigation();

const breadcrumbs = usersCreateBreadcrumbs;

const defaultForm = (): UserEditorFields => ({
    name: '',
    username: '',
    email: '',
    avatar: null,
    remove_avatar: false,
    status:
        props.statuses.find((s) => s.value === 'active')?.value ??
        props.statuses[0]?.value ??
        'active',
    locale: props.locales[0]?.value ?? 'en',
    timezone: 'UTC',
    roles: [],
    permissions: [],
    first_name: '',
    last_name: '',
    phone_number: '',
    date_of_birth: '',
    gender: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    company_name: '',
    job_title: '',
    bio: '',
    social_links: {
        linkedin: '',
        twitter: '',
        github: '',
    },
    password: '',
    password_confirmation: '',
});

const form = ref<UserEditorFields>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

function goBack() {
    router.visit(index.url());
}

function submit() {
    processing.value = true;
    errors.value = {};

    router.post(store.url(), form.value, {
        forceFormData: true,
        preserveScroll: true,
        onError: (responseErrors) => {
            errors.value = responseErrors as Partial<Record<string, string>>;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="__('New user')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <!-- Page header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    :aria-label="__('Back to users')"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                </Button>
                <div class="space-y-0.5">
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ __('New user') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ __('Create a new managed user account.') }}
                    </p>
                </div>
            </div>

            <!-- Form card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <form class="space-y-6 p-6 md:p-8" @submit.prevent="submit">
                    <UserForm
                        v-model="form"
                        :errors="errors"
                        :roles="props.roles"
                        :permissions="props.permissions"
                        :locales="props.locales"
                        :statuses="props.statuses"
                        :timezones="props.timezones"
                        show-password-fields
                    />

                    <Separator />

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="goBack"
                        >
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            {{ __('Create user') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
