<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
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
import { store, update } from '@/routes/cumpu/approval-rules';
import type { SelectOption } from '@/types';
import type {
    ApprovalRule,
    ApprovalRuleFormPayload,
    RoleOption,
} from '@/types/cumpu';

type ActionOption = SelectOption & { permission: string };

type PermissionOptions = {
    apps: SelectOption[];
    resources: Record<string, SelectOption[]>;
    actions: Record<string, Record<string, ActionOption[]>>;
};

const props = defineProps<{
    open: boolean;
    editingRule?: ApprovalRule | null;
    permissionOptions: PermissionOptions;
    roles: RoleOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

const defaultForm = (): ApprovalRuleFormPayload => ({
    app_key: '',
    resource_key: '',
    action_key: '',
    permission_name: '',
    workflow_type: 'single',
    step_role_id: null,
    enabled: false,
});

const form = ref<ApprovalRuleFormPayload>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const isHydrating = ref(false);

const isEditing = computed(() => Boolean(props.editingRule));

const resourceOptions = computed<SelectOption[]>(
    () => props.permissionOptions.resources[form.value.app_key] ?? [],
);

const actionOptions = computed<ActionOption[]>(
    () =>
        props.permissionOptions.actions[form.value.app_key]?.[
            form.value.resource_key
        ] ?? [],
);

const roleOptions = computed(() => props.roles);

watch(
    () => props.editingRule,
    (rule) => {
        isHydrating.value = true;

        if (rule) {
            form.value = {
                app_key: rule.app_key,
                resource_key: rule.resource_key,
                action_key: rule.action_key,
                permission_name: rule.permission_name,
                workflow_type: rule.workflow_type,
                step_role_id: rule.step_role_id,
                enabled: rule.enabled,
            };
        } else {
            form.value = defaultForm();
            if (props.permissionOptions.apps[0]) {
                form.value.app_key = props.permissionOptions.apps[0].value;
            }
        }

        errors.value = {};

        void nextTick(() => {
            isHydrating.value = false;
        });
    },
    { immediate: true },
);

watch(
    () => form.value.app_key,
    (appKey, previousAppKey) => {
        if (isHydrating.value || appKey === previousAppKey) {
            return;
        }

        form.value.resource_key = '';
        form.value.action_key = '';
        form.value.permission_name = '';
    },
);

watch(
    () => form.value.resource_key,
    (resourceKey, previousResourceKey) => {
        if (isHydrating.value || resourceKey === previousResourceKey) {
            return;
        }

        form.value.action_key = '';
        form.value.permission_name = '';
    },
);

watch(
    () => form.value.action_key,
    (actionKey) => {
        const matched = actionOptions.value.find((a) => a.value === actionKey);
        form.value.permission_name = matched?.permission ?? '';
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    processing.value = true;
    errors.value = {};

    if (isEditing.value && props.editingRule) {
        router.patch(
            update.url({ approvalRule: props.editingRule.id }),
            form.value,
            {
                preserveScroll: true,
                onSuccess: () => close(),
                onError: (responseErrors) => {
                    errors.value = responseErrors as Partial<
                        Record<string, string>
                    >;
                },
                onFinish: () => {
                    processing.value = false;
                },
            },
        );
    } else {
        router.post(store.url(), form.value, {
            preserveScroll: true,
            onSuccess: () => close(),
            onError: (responseErrors) => {
                errors.value = responseErrors as Partial<
                    Record<string, string>
                >;
            },
            onFinish: () => {
                processing.value = false;
            },
        });
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing
                            ? __('Edit approval rule')
                            : __('New approval rule')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? __(
                                  'Update which role reviews this action and whether the rule is active.',
                              )
                            : __(
                                  'Define which action requires approval and who reviews it.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- App + Resource -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label
                            for="rule-app"
                            class="text-xs font-medium tracking-wide uppercase"
                        >
                            {{ __('App') }}
                        </Label>
                        <Select
                            :model-value="form.app_key"
                            :disabled="isEditing"
                            @update:model-value="form.app_key = String($event)"
                        >
                            <SelectTrigger id="rule-app" class="w-full">
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
                        <FormFieldSupport :error="errors.app_key" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="rule-resource"
                            class="text-xs font-medium tracking-wide uppercase"
                        >
                            {{ __('Resource') }}
                        </Label>
                        <Select
                            :model-value="form.resource_key"
                            :disabled="
                                isEditing || resourceOptions.length === 0
                            "
                            @update:model-value="
                                form.resource_key = String($event)
                            "
                        >
                            <SelectTrigger id="rule-resource" class="w-full">
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
                        <FormFieldSupport :error="errors.resource_key" />
                    </div>
                </div>

                <!-- Action -->
                <div class="grid gap-1.5">
                    <Label
                        for="rule-action"
                        class="text-xs font-medium tracking-wide uppercase"
                    >
                        {{ __('Action') }}
                    </Label>
                    <Select
                        :model-value="form.action_key"
                        :disabled="isEditing || actionOptions.length === 0"
                        @update:model-value="form.action_key = String($event)"
                    >
                        <SelectTrigger id="rule-action" class="w-full">
                            <SelectValue :placeholder="__('Select action')" />
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
                    <FormFieldSupport
                        v-if="form.permission_name"
                        :hint="`${__('Permission')}: ${form.permission_name}`"
                        :error="errors.action_key"
                    />
                    <FormFieldSupport v-else :error="errors.action_key" />
                </div>

                <!-- Reviewer role -->
                <div class="grid gap-1.5">
                    <Label
                        for="rule-role"
                        class="text-xs font-medium tracking-wide uppercase"
                    >
                        {{ __('Reviewer role') }}
                    </Label>
                    <Select
                        :model-value="
                            form.step_role_id !== null
                                ? String(form.step_role_id)
                                : ''
                        "
                        @update:model-value="
                            form.step_role_id = $event ? Number($event) : null
                        "
                    >
                        <SelectTrigger id="rule-role" class="w-full">
                            <SelectValue :placeholder="__('Select role')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="role in roleOptions"
                                :key="role.value"
                                :value="String(role.value)"
                            >
                                {{ role.label }}
                                <span
                                    class="ml-1.5 text-xs text-muted-foreground"
                                >
                                    ({{
                                        __('level :n', {
                                            n: String(role.level),
                                        })
                                    }})
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <FormFieldSupport
                        :error="errors.step_role_id ?? errors.role_id"
                    />
                </div>

                <!-- Enabled -->
                <div class="flex items-start gap-2.5">
                    <Checkbox
                        id="rule-enabled"
                        :model-value="form.enabled"
                        @update:model-value="form.enabled = Boolean($event)"
                    />
                    <div class="grid gap-0.5">
                        <Label
                            for="rule-enabled"
                            class="cursor-pointer text-sm font-medium"
                        >
                            {{ __('Enabled') }}
                        </Label>
                        <p class="text-xs text-muted-foreground">
                            {{
                                __(
                                    'When disabled, the action proceeds without requiring approval.',
                                )
                            }}
                        </p>
                    </div>
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
                    {{ isEditing ? __('Save changes') : __('Create rule') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
