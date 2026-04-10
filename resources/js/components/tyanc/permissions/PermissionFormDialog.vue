<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store, update } from '@/routes/tyanc/permissions';
import type { PermissionRow, SelectOption } from '@/types';

type PermissionFormFields = {
    app_key: string;
    resource_key: string;
    action_key: string;
    roles: string[];
};

const props = defineProps<{
    open: boolean;
    editingPermission?: PermissionRow | null;
    roles: SelectOption[];
    apps: SelectOption[];
    resourceCatalog: Record<string, SelectOption[]>;
    actions: SelectOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

const defaultForm = (): PermissionFormFields => ({
    app_key: '',
    resource_key: '',
    action_key: '',
    roles: [],
});

const form = ref<PermissionFormFields>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

const isEditing = computed(() => Boolean(props.editingPermission));
const title = computed(() =>
    isEditing.value ? __('Edit permission') : __('New permission'),
);
const appOptions = computed<SelectOption[]>(() => {
    if (
        form.value.app_key !== '' &&
        !props.apps.some((option) => option.value === form.value.app_key)
    ) {
        return [
            ...props.apps,
            {
                value: form.value.app_key,
                label: form.value.app_key,
            },
        ];
    }

    return props.apps;
});
const resourceOptions = computed<SelectOption[]>(() => {
    const options = props.resourceCatalog[form.value.app_key] ?? [];

    if (
        form.value.resource_key !== '' &&
        !options.some((option) => option.value === form.value.resource_key)
    ) {
        return [
            ...options,
            {
                value: form.value.resource_key,
                label: form.value.resource_key,
            },
        ];
    }

    return options;
});
const actionOptions = computed<SelectOption[]>(() => {
    if (
        form.value.action_key !== '' &&
        !props.actions.some((option) => option.value === form.value.action_key)
    ) {
        return [
            ...props.actions,
            {
                value: form.value.action_key,
                label: form.value.action_key,
            },
        ];
    }

    return props.actions;
});
const permissionName = computed(() =>
    [form.value.app_key, form.value.resource_key, form.value.action_key]
        .map((segment) => segment.trim())
        .filter(Boolean)
        .join('.'),
);

watch(
    () => props.editingPermission,
    (permission) => {
        if (permission) {
            form.value = {
                app_key: permission.app ?? '',
                resource_key: permission.resource ?? '',
                action_key: permission.action ?? '',
                roles: permission.roles ?? [],
            };
        } else {
            form.value = defaultForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

watch(
    () => form.value.app_key,
    (nextAppKey, previousAppKey) => {
        if (nextAppKey === previousAppKey) {
            return;
        }

        const nextResources = props.resourceCatalog[nextAppKey] ?? [];

        if (
            !nextResources.some(
                (option) => option.value === form.value.resource_key,
            )
        ) {
            form.value.resource_key = nextResources[0]?.value ?? '';
        }
    },
);

function toggleRole(value: string, checked: boolean) {
    form.value.roles = checked
        ? [...form.value.roles, value]
        : form.value.roles.filter((role) => role !== value);
}

function close() {
    emit('update:open', false);
}

function submit() {
    processing.value = true;
    errors.value = {};

    const payload = {
        app_key: form.value.app_key,
        resource_key: form.value.resource_key,
        action_key: form.value.action_key,
        roles: form.value.roles,
    };

    const isEdit = isEditing.value && props.editingPermission;
    const url = isEdit
        ? update.url({ permission: props.editingPermission!.id })
        : store.url();
    const method = isEdit ? 'patch' : 'post';

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => close(),
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
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? __('Update the permission definition.')
                            : __(
                                  'Define a new permission for the access control system.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="perm-app">{{ __('App') }}</Label>
                        <Select
                            :model-value="form.app_key"
                            @update:model-value="form.app_key = String($event)"
                        >
                            <SelectTrigger id="perm-app" class="w-full">
                                <SelectValue :placeholder="__('Select app')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="app in appOptions"
                                    :key="app.value"
                                    :value="app.value"
                                >
                                    {{ app.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.app_key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="perm-resource">{{ __('Resource') }}</Label>
                        <Select
                            :model-value="form.resource_key"
                            @update:model-value="
                                form.resource_key = String($event)
                            "
                        >
                            <SelectTrigger id="perm-resource" class="w-full">
                                <SelectValue
                                    :placeholder="__('Select resource')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="resource in resourceOptions"
                                    :key="resource.value"
                                    :value="resource.value"
                                >
                                    {{ resource.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.resource_key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="perm-action">{{ __('Action') }}</Label>
                        <Select
                            :model-value="form.action_key"
                            @update:model-value="
                                form.action_key = String($event)
                            "
                        >
                            <SelectTrigger id="perm-action" class="w-full">
                                <SelectValue
                                    :placeholder="__('Select action')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="action in actionOptions"
                                    :key="action.value"
                                    :value="action.value"
                                >
                                    {{ action.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.action_key" />
                    </div>
                </div>

                <FormFieldSupport
                    :hint="
                        __(
                            'Permissions are generated from the selected app, resource, and action.',
                        )
                    "
                    :error="errors.name"
                />

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2.5"
                >
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ __('Preview') }}
                    </p>
                    <p class="mt-1 font-mono text-sm text-foreground">
                        {{ permissionName || __('app.resource.action') }}
                    </p>
                </div>

                <div v-if="props.roles.length > 0" class="space-y-2">
                    <Label>{{ __('Assigned roles') }}</Label>
                    <div
                        class="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-sidebar-border/70 p-2"
                    >
                        <div
                            v-for="role in props.roles"
                            :key="role.value"
                            class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted/40"
                        >
                            <Checkbox
                                :id="`perm-role-${role.value}`"
                                :checked="form.roles.includes(role.value)"
                                @update:checked="
                                    toggleRole(role.value, Boolean($event))
                                "
                            />
                            <Label
                                :for="`perm-role-${role.value}`"
                                class="cursor-pointer"
                                >{{ role.label }}</Label
                            >
                        </div>
                    </div>
                    <InputError :message="errors.roles" />
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="close"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button :disabled="processing" @click="submit">
                    <Spinner v-if="processing" />
                    {{
                        isEditing ? __('Save changes') : __('Create permission')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
