<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import RolePermissionActionChecklist from '@/components/tyanc/roles/RolePermissionActionChecklist.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { update as updateRolePermissions } from '@/routes/tyanc/roles/permissions';
import type { PermissionOptions, RoleRow, SelectOption } from '@/types';

const props = defineProps<{
    open: boolean;
    role: RoleRow | null;
    permissionOptions: PermissionOptions;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

const selectedApp = ref('');
const selectedResource = ref('');
const selectedPermissions = ref<string[]>([]);
const processing = ref(false);
const errorMessage = ref<string | null>(null);

/** Reset selections whenever the dialog opens or role changes. */
watch(
    [() => props.open, () => props.role],
    ([open]) => {
        if (!open) return;
        selectedApp.value = props.permissionOptions.apps[0]?.value ?? '';
        selectedResource.value = '';
        selectedPermissions.value = props.role?.permissions
            ? [...props.role.permissions]
            : [];
        errorMessage.value = null;
    },
    { immediate: true },
);

/** Auto-select first resource when app changes. */
watch(selectedApp, (app) => {
    const resources = props.permissionOptions.resources[app] ?? [];
    selectedResource.value = resources[0]?.value ?? '';
});

const resourceOptions = computed<SelectOption[]>(
    () => props.permissionOptions.resources[selectedApp.value] ?? [],
);

type ActionOption = SelectOption & { permission: string };
const actionOptions = computed<ActionOption[]>(
    () =>
        props.permissionOptions.actions[selectedApp.value]?.[
            selectedResource.value
        ] ?? [],
);

function close() {
    emit('update:open', false);
}

function submit() {
    if (!props.role) return;

    processing.value = true;
    errorMessage.value = null;

    router.patch(
        updateRolePermissions.url({ role: props.role.id }),
        { permissions: selectedPermissions.value },
        {
            preserveScroll: true,
            onSuccess: () => close(),
            onError: (errors) => {
                errorMessage.value =
                    typeof errors.permissions === 'string'
                        ? errors.permissions
                        : __('Unable to save permissions. Please try again.');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ __('Assign permissions') }}
                    <span
                        v-if="props.role"
                        class="ml-1.5 font-normal text-muted-foreground"
                    >
                        — {{ props.role.name }}
                    </span>
                </DialogTitle>
                <DialogDescription>
                    {{
                        __(
                            'Select an app and resource, then check the actions this role should be able to perform.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Step 1 & 2: App + Resource selectors -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <label
                            for="perm-assign-app"
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('App') }}
                        </label>
                        <Select
                            :model-value="selectedApp"
                            @update:model-value="selectedApp = String($event)"
                        >
                            <SelectTrigger id="perm-assign-app" class="w-full">
                                <SelectValue :placeholder="__('Select app')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="app in props.permissionOptions.apps"
                                    :key="app.value"
                                    :value="app.value"
                                >
                                    {{ app.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-1.5">
                        <label
                            for="perm-assign-resource"
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Resource') }}
                        </label>
                        <Select
                            :model-value="selectedResource"
                            :disabled="resourceOptions.length === 0"
                            @update:model-value="
                                selectedResource = String($event)
                            "
                        >
                            <SelectTrigger
                                id="perm-assign-resource"
                                class="w-full"
                            >
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
                    </div>
                </div>

                <!-- Step 3: Action checkboxes -->
                <div class="space-y-1.5">
                    <div>
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ __('Actions') }}
                        </p>
                    </div>
                    <RolePermissionActionChecklist
                        :actions="actionOptions"
                        :model-value="selectedPermissions"
                        @update:model-value="selectedPermissions = $event"
                    />
                </div>

                <p v-if="errorMessage" class="text-sm text-destructive">
                    {{ errorMessage }}
                </p>

                <!-- Summary -->
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-2.5"
                >
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ __('Total assigned') }}
                    </p>
                    <p
                        class="mt-0.5 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{
                            __(':n permission(s)', {
                                n: String(selectedPermissions.length),
                            })
                        }}
                    </p>
                </div>
            </div>

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
                    {{ __('Save permissions') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
