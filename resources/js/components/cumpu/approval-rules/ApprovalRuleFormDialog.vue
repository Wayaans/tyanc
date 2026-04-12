<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { GripVertical, Plus, Trash2 } from 'lucide-vue-next';
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
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { store, update } from '@/routes/cumpu/approval-rules';
import type { SelectOption } from '@/types';
import type {
    ApprovalRule,
    ApprovalRuleFormPayload,
    ApprovalRuleStepFormData,
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

const makeDefaultStep = (order = 1): ApprovalRuleStepFormData => ({
    label: '',
    role_id: null,
    order,
});

const defaultForm = (): ApprovalRuleFormPayload => ({
    app_key: '',
    resource_key: '',
    action_key: '',
    permission_name: '',
    workflow_type: 'single',
    grant_validity_minutes: 1440,
    steps: [makeDefaultStep(1)],
    reminder_after_minutes: null,
    escalation_after_minutes: null,
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

watch(
    () => props.editingRule,
    (rule) => {
        isHydrating.value = true;

        if (rule) {
            const steps: ApprovalRuleStepFormData[] =
                rule.steps && rule.steps.length > 0
                    ? rule.steps.map((s) => ({
                          label: s.label,
                          role_id: s.role_id,
                          order: s.order,
                      }))
                    : [
                          {
                              label: rule.step_label ?? '',
                              role_id: rule.step_role_id,
                              order: 1,
                          },
                      ];

            form.value = {
                app_key: rule.app_key,
                resource_key: rule.resource_key,
                action_key: rule.action_key,
                permission_name: rule.permission_name,
                workflow_type: rule.workflow_type,
                grant_validity_minutes: rule.grant_validity_minutes,
                steps,
                reminder_after_minutes: rule.reminder_after_minutes,
                escalation_after_minutes: rule.escalation_after_minutes,
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

// Keep steps in sync with workflow type
watch(
    () => form.value.workflow_type,
    (type) => {
        if (isHydrating.value) {
            return;
        }
        if (type === 'single') {
            // Collapse to one step
            form.value.steps = [form.value.steps[0] ?? makeDefaultStep(1)];
        } else if (form.value.steps.length === 0) {
            form.value.steps = [makeDefaultStep(1)];
        }
    },
);

function addStep() {
    form.value.steps.push(makeDefaultStep(form.value.steps.length + 1));
}

function removeStep(index: number) {
    if (form.value.steps.length <= 1) {
        return;
    }
    form.value.steps.splice(index, 1);
    // Reorder
    form.value.steps.forEach((s, i) => {
        s.order = i + 1;
    });
}

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

            <form
                class="max-h-[70vh] space-y-4 overflow-y-auto pr-1"
                @submit.prevent="submit"
            >
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

                <Separator />

                <!-- Workflow type -->
                <div class="grid gap-1.5">
                    <Label class="text-xs font-medium tracking-wide uppercase">
                        {{ __('Workflow') }}
                    </Label>
                    <div class="flex gap-3">
                        <label
                            class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors"
                            :class="
                                form.workflow_type === 'single'
                                    ? 'border-primary/50 bg-primary/5'
                                    : 'border-sidebar-border/70 hover:bg-sidebar/10'
                            "
                        >
                            <input
                                type="radio"
                                name="workflow_type"
                                value="single"
                                class="sr-only"
                                :checked="form.workflow_type === 'single'"
                                @change="form.workflow_type = 'single'"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-foreground">
                                    {{ __('Single step') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ __('One reviewer role per request.') }}
                                </p>
                            </div>
                        </label>
                        <label
                            class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 transition-colors"
                            :class="
                                form.workflow_type === 'multi'
                                    ? 'border-primary/50 bg-primary/5'
                                    : 'border-sidebar-border/70 hover:bg-sidebar/10'
                            "
                        >
                            <input
                                type="radio"
                                name="workflow_type"
                                value="multi"
                                class="sr-only"
                                :checked="form.workflow_type === 'multi'"
                                @change="form.workflow_type = 'multi'"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-foreground">
                                    {{ __('Multi-step') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ __('Sequential reviewer chain.') }}
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Steps -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label
                            class="text-xs font-medium tracking-wide uppercase"
                        >
                            {{ __('Steps') }}
                        </Label>
                        <Button
                            v-if="form.workflow_type === 'multi'"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 gap-1 text-xs"
                            @click="addStep"
                        >
                            <Plus class="size-3" />
                            {{ __('Add step') }}
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(step, index) in form.steps"
                            :key="index"
                            class="flex items-start gap-2 rounded-lg border border-sidebar-border/70 bg-sidebar/10 px-3 py-3"
                        >
                            <!-- Drag handle (visual only) -->
                            <GripVertical
                                v-if="form.workflow_type === 'multi'"
                                class="mt-2 size-3.5 shrink-0 text-muted-foreground/40"
                            />

                            <div class="flex-1 space-y-2">
                                <!-- Step order label -->
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{
                                        __('Step :n', { n: String(index + 1) })
                                    }}
                                </p>

                                <!-- Label (optional) -->
                                <Input
                                    v-if="form.workflow_type === 'multi'"
                                    :id="`step-label-${index}`"
                                    v-model="step.label"
                                    :placeholder="__('Step label (optional)')"
                                    class="h-8 text-sm"
                                />

                                <!-- Role -->
                                <Select
                                    :model-value="
                                        step.role_id !== null
                                            ? String(step.role_id)
                                            : ''
                                    "
                                    @update:model-value="
                                        step.role_id = $event
                                            ? Number($event)
                                            : null
                                    "
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue
                                            :placeholder="
                                                __('Select reviewer role')
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in props.roles"
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
                                    :error="errors[`steps.${index}.role_id`]"
                                />
                            </div>

                            <!-- Remove step -->
                            <Button
                                v-if="
                                    form.workflow_type === 'multi' &&
                                    form.steps.length > 1
                                "
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="mt-1 size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                :aria-label="__('Remove step')"
                                @click="removeStep(index)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Timing config -->
                <div class="space-y-3">
                    <Label class="text-xs font-medium tracking-wide uppercase">
                        {{ __('Timing') }}
                    </Label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="reminder-minutes"
                                class="text-xs text-muted-foreground"
                            >
                                {{ __('Reminder after (minutes)') }}
                            </Label>
                            <Input
                                id="reminder-minutes"
                                :model-value="
                                    form.reminder_after_minutes !== null
                                        ? String(form.reminder_after_minutes)
                                        : ''
                                "
                                type="number"
                                min="1"
                                class="h-8 text-sm"
                                :placeholder="__('e.g. 1440')"
                                @update:model-value="
                                    form.reminder_after_minutes = $event
                                        ? Number($event)
                                        : null
                                "
                            />
                            <FormFieldSupport
                                :hint="__('Leave blank to disable reminders.')"
                                :error="errors.reminder_after_minutes"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="escalation-minutes"
                                class="text-xs text-muted-foreground"
                            >
                                {{ __('Escalate after (minutes)') }}
                            </Label>
                            <Input
                                id="escalation-minutes"
                                :model-value="
                                    form.escalation_after_minutes !== null
                                        ? String(form.escalation_after_minutes)
                                        : ''
                                "
                                type="number"
                                min="1"
                                class="h-8 text-sm"
                                :placeholder="__('e.g. 2880')"
                                @update:model-value="
                                    form.escalation_after_minutes = $event
                                        ? Number($event)
                                        : null
                                "
                            />
                            <FormFieldSupport
                                :hint="__('Leave blank to disable escalation.')"
                                :error="errors.escalation_after_minutes"
                            />
                        </div>
                    </div>

                    <!-- Grant validity -->
                    <div class="grid gap-1.5">
                        <Label
                            for="grant-validity-minutes"
                            class="text-xs text-muted-foreground"
                        >
                            {{ __('Grant validity (minutes)') }}
                        </Label>
                        <Input
                            id="grant-validity-minutes"
                            :model-value="
                                form.grant_validity_minutes !== null
                                    ? String(form.grant_validity_minutes)
                                    : ''
                            "
                            type="number"
                            min="5"
                            class="h-8 text-sm"
                            :placeholder="__('e.g. 1440')"
                            @update:model-value="
                                form.grant_validity_minutes = $event
                                    ? Number($event)
                                    : null
                            "
                        />
                        <FormFieldSupport
                            :hint="
                                __(
                                    'How long the one-time grant stays valid after approval. The requester must retry the action within this window to consume it.',
                                )
                            "
                            :error="errors.grant_validity_minutes"
                        />
                    </div>
                </div>

                <Separator />

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
