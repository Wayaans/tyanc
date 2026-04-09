<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import UserForm, {
    type UserEditorFields,
} from '@/components/tyanc/users/UserForm.vue';
import UserStatusBadge from '@/components/tyanc/users/UserStatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { destroy, index, show, suspend, update } from '@/routes/tyanc/users';
import type { RoleOption, SelectOption, UserFormData } from '@/types';

const props = defineProps<{
    user: UserFormData;
    roles: RoleOption[];
    permissions: SelectOption[];
    locales: SelectOption[];
    statuses: SelectOption[];
    timezones: string[];
}>();

const { __ } = useTranslations();
const { usersEditBreadcrumbs } = useAppNavigation();

const breadcrumbs = usersEditBreadcrumbs(props.user.name, props.user.id);

function fromUserFormData(user: UserFormData): UserEditorFields {
    return {
        name: user.name,
        username: user.username ?? '',
        email: user.email,
        avatar: null,
        remove_avatar: false,
        status: user.status,
        locale: user.locale,
        timezone: user.timezone,
        roles: [...user.roles],
        permissions: [...user.permissions],
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        phone_number: user.phone_number ?? '',
        date_of_birth: user.date_of_birth ?? '',
        gender: user.gender ?? '',
        address_line_1: user.address_line_1 ?? '',
        address_line_2: user.address_line_2 ?? '',
        city: user.city ?? '',
        state: user.state ?? '',
        country: user.country ?? '',
        postal_code: user.postal_code ?? '',
        company_name: user.company_name ?? '',
        job_title: user.job_title ?? '',
        bio: user.bio ?? '',
        social_links: {
            linkedin: user.social_links?.linkedin ?? '',
            twitter: user.social_links?.twitter ?? '',
            github: user.social_links?.github ?? '',
        },
        password: '',
        password_confirmation: '',
    };
}

const form = ref<UserEditorFields>(fromUserFormData(props.user));
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const suspendProcessing = ref(false);
const deleteProcessing = ref(false);
const confirmingDelete = ref(false);

function goBack() {
    router.visit(show.url({ user: props.user.id }));
}

function goToIndex() {
    router.visit(index.url());
}

function submit() {
    processing.value = true;
    errors.value = {};

    router.patch(update.url({ user: props.user.id }), form.value, {
        preserveScroll: true,
        onError: (responseErrors) => {
            errors.value = responseErrors as Partial<Record<string, string>>;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function suspendUser() {
    suspendProcessing.value = true;

    router.patch(
        suspend.url({ user: props.user.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                suspendProcessing.value = false;
            },
        },
    );
}

function handleDelete() {
    if (!confirmingDelete.value) {
        confirmingDelete.value = true;

        return;
    }

    deleteProcessing.value = true;

    router.delete(destroy.url({ user: props.user.id }), {
        onSuccess: () => {
            goToIndex();
        },
        onFinish: () => {
            deleteProcessing.value = false;
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <Head :title="__('Edit :name', { name: props.user.name })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <!-- Page header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    :aria-label="__('Back to user details')"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                </Button>
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <Avatar class="size-9 shrink-0">
                        <AvatarImage
                            :src="props.user.avatar ?? ''"
                            :alt="props.user.name"
                        />
                        <AvatarFallback class="text-xs">
                            {{ getInitials(props.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 space-y-0.5">
                        <h1
                            class="truncate text-xl font-semibold tracking-tight text-foreground"
                        >
                            {{ __('Edit :name', { name: props.user.name }) }}
                        </h1>
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm text-muted-foreground">
                                {{ props.user.email }}
                            </p>
                            <UserStatusBadge :status="props.user.status" />
                        </div>
                    </div>
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
                        :current-avatar-url="props.user.avatar"
                        show-password-fields
                        password-optional
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
                            {{ __('Save changes') }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Danger zone card -->
            <div
                class="overflow-hidden rounded-2xl border border-destructive/20 bg-background/90"
            >
                <div class="p-6 md:p-8">
                    <h2 class="text-sm font-semibold text-foreground">
                        {{ __('Account actions') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            __(
                                'These actions are irreversible or have significant consequences.',
                            )
                        }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="
                                suspendProcessing ||
                                processing ||
                                props.user.status === 'suspended'
                            "
                            @click="suspendUser"
                        >
                            <Spinner v-if="suspendProcessing" />
                            {{ __('Suspend account') }}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :class="[
                                confirmingDelete
                                    ? 'border-destructive text-destructive hover:bg-destructive/10'
                                    : 'text-destructive/80 hover:border-destructive hover:text-destructive',
                            ]"
                            :disabled="deleteProcessing || processing"
                            @click="handleDelete"
                        >
                            <AlertTriangle
                                v-if="confirmingDelete"
                                class="size-4"
                            />
                            <Spinner v-else-if="deleteProcessing" />
                            {{
                                confirmingDelete
                                    ? __('Click again to confirm deletion')
                                    : __('Delete this account')
                            }}
                        </Button>

                        <Button
                            v-if="confirmingDelete"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="confirmingDelete = false"
                        >
                            {{ __('Cancel') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
