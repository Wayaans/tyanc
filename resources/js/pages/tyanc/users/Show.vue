<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    AtSign,
    Clock,
    Key,
    Mail,
    Shield,
    ShieldOff,
    Trash2,
    UserPen,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserStatusBadge from '@/components/tyanc/users/UserStatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { destroy, edit, index, suspend } from '@/routes/tyanc/users';
import type { UserFormData } from '@/types';

type Abilities = {
    update: boolean;
    suspend: boolean;
    delete: boolean;
};

const props = defineProps<{
    user: UserFormData;
    abilities: Abilities;
}>();

const { __, locale } = useTranslations();
const { usersShowBreadcrumbs } = useAppNavigation();

const breadcrumbs = usersShowBreadcrumbs(props.user.name, props.user.id);

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return dateFormatter.value.format(new Date(date));
}

const suspendProcessing = ref(false);
const deleteProcessing = ref(false);
const confirmingDelete = ref(false);

function goBack() {
    router.visit(index.url());
}

function goToEdit() {
    router.visit(edit.url({ user: props.user.id }));
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
            router.visit(index.url());
        },
        onFinish: () => {
            deleteProcessing.value = false;
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <Head :title="props.user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
            <!-- Back nav -->
            <div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="gap-1.5 text-muted-foreground hover:text-foreground"
                    @click="goBack"
                >
                    <ArrowLeft class="size-3.5" />
                    {{ __('All users') }}
                </Button>
            </div>

            <!-- Hero card -->
            <div
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
            >
                <div class="p-6 md:p-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                        <!-- Avatar -->
                        <Avatar class="size-20 shrink-0 text-xl">
                            <AvatarImage
                                :src="props.user.avatar ?? ''"
                                :alt="props.user.name"
                            />
                            <AvatarFallback class="text-xl">
                                {{ getInitials(props.user.name) }}
                            </AvatarFallback>
                        </Avatar>

                        <!-- Identity -->
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="space-y-1">
                                <h1
                                    class="text-2xl font-bold tracking-tight text-foreground"
                                >
                                    {{ props.user.name }}
                                </h1>
                                <div
                                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                                >
                                    <span class="flex items-center gap-1">
                                        <Mail class="size-3.5 shrink-0" />
                                        {{ props.user.email }}
                                    </span>
                                    <span
                                        v-if="props.user.username"
                                        class="flex items-center gap-1 font-mono"
                                    >
                                        <AtSign class="size-3.5 shrink-0" />
                                        {{ props.user.username }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <UserStatusBadge :status="props.user.status" />
                                <Badge
                                    v-for="role in props.user.roles"
                                    :key="role"
                                    variant="secondary"
                                    class="rounded-full"
                                >
                                    {{ role }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Quick actions -->
                        <div
                            class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end"
                        >
                            <Button
                                v-if="props.abilities.update"
                                size="sm"
                                class="gap-2"
                                @click="goToEdit"
                            >
                                <UserPen class="size-4" />
                                {{ __('Edit user') }}
                            </Button>

                            <Button
                                v-if="
                                    props.abilities.suspend &&
                                    props.user.status !== 'suspended'
                                "
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                :disabled="suspendProcessing"
                                @click="suspendUser"
                            >
                                <Spinner v-if="suspendProcessing" />
                                <ShieldOff v-else class="size-4" />
                                {{ __('Suspend') }}
                            </Button>

                            <Button
                                v-if="props.abilities.delete"
                                variant="outline"
                                size="sm"
                                :class="[
                                    'gap-2',
                                    confirmingDelete
                                        ? 'border-destructive text-destructive hover:bg-destructive/10'
                                        : 'text-destructive/70 hover:border-destructive hover:text-destructive',
                                ]"
                                :disabled="deleteProcessing"
                                @click="handleDelete"
                            >
                                <Spinner v-if="deleteProcessing" />
                                <Trash2 v-else class="size-4" />
                                {{
                                    confirmingDelete
                                        ? __('Confirm deletion')
                                        : __('Delete')
                                }}
                            </Button>

                            <Button
                                v-if="confirmingDelete"
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

            <!-- Detail cards grid -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Account card -->
                <div
                    class="rounded-2xl border border-sidebar-border/70 bg-background/90 p-6"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <Shield class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ __('Account') }}
                        </h2>
                    </div>
                    <dl class="space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Username') }}
                            </dt>
                            <dd class="truncate text-right font-mono text-sm">
                                {{
                                    props.user.username
                                        ? `@${props.user.username}`
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Email verified') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{
                                    props.user.email_verified_at
                                        ? formatDate(
                                              props.user.email_verified_at,
                                          )
                                        : __('Not verified')
                                }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Locale') }}
                            </dt>
                            <dd class="text-right text-sm uppercase">
                                {{ props.user.locale }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Timezone') }}
                            </dt>
                            <dd class="truncate text-right text-sm">
                                {{ props.user.timezone }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt
                                class="flex shrink-0 items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Clock class="size-3.5" />
                                {{ __('Last login') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{ formatDate(props.user.last_login_at) }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Last login IP') }}
                            </dt>
                            <dd class="text-right font-mono text-sm">
                                {{ props.user.last_login_ip ?? '—' }}
                            </dd>
                        </div>
                        <Separator />
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-sm text-muted-foreground">
                                {{ __('Member since') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{ formatDate(props.user.created_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Roles & Permissions card -->
                <div
                    class="rounded-2xl border border-sidebar-border/70 bg-background/90 p-6"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <Key class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold text-foreground">
                            {{ __('Roles & permissions') }}
                        </h2>
                    </div>

                    <div class="space-y-5">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Roles') }}
                            </p>
                            <div
                                v-if="props.user.roles.length > 0"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="role in props.user.roles"
                                    :key="role"
                                    variant="secondary"
                                    class="rounded-full"
                                >
                                    {{ role }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ __('No roles assigned.') }}
                            </p>
                        </div>

                        <Separator />

                        <div class="space-y-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ __('Direct permissions') }}
                            </p>
                            <div
                                v-if="props.user.permissions.length > 0"
                                class="flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    v-for="permission in props.user.permissions"
                                    :key="permission"
                                    variant="outline"
                                    class="rounded-full font-mono text-xs"
                                >
                                    {{ permission }}
                                </Badge>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ __('No direct permissions.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
