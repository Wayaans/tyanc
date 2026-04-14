<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { GripVertical, Info, Plus, Settings2, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { toggle, update } from '@/routes/cumpu/approval-rules';
import type {
    ApprovalRuleStepFormData,
    ManagedApprovalRule,
    RoleOption,
} from '@/types/cumpu';

const props = defineProps<{
    open: boolean;
    rule: ManagedApprovalRule | null;
    roles: RoleOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { __ } = useTranslations();

type WorkflowType = 'single' | 'multi';

type FormState = {
    workflow_type: WorkflowType;
    steps: ApprovalRuleStepFormData[];
    grant_validity_minutes: number | null;
    reminder_after_minutes: number | null;
    escalation_after_minutes: number | null;
};

const makeDefaultStep = (order = 1): ApprovalRuleStepFormData => ({
    label: '',
    role_id: null,
    order,
});

const defaultForm = (): FormState => ({
    workflow_type: 'single',
    steps: [makeDefaultStep(1)],
    grant_validity_minutes: 1440,
    reminder_after_minutes: null,
    escalation_after_minutes: null,
});

const form = ref<FormState>(defaultForm());
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const isHydrating = ref(false);
const togglingEnabled = ref(false);

const isPendingSync = computed(
    () => props.rule?.sync_state === 'pending_sync' || props.rule?.id === null,
);

const isRemoved = computed(() => props.rule?.sync_state === 'removed');

const canToggle = computed(() => {
    const rule = props.rule;
    if (!rule?.id || isPendingSync.value || isRemoved.value) {
        return false;
    }
    return (
        rule.toggleable &&
        ['synced', 'incomplete'].includes(rule.sync_state) &&
        (rule.enabled || rule.is_ready)
    );
});

function doToggle() {
    if (
        !props.rule?.id ||
        !canToggle.value ||
        togglingEnabled.value ||
        processing.value
    ) {
        return;
    }

    togglingEnabled.value = true;

    router.patch(
        toggle.url({ approvalRule: props.rule.id }),
        { enabled: !props.rule.enabled },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['rules'],
            onFinish: () => {
                togglingEnabled.value = false;
            },
        },
    );
}

function modeLabel(mode: ManagedApprovalRule['mode']): string {
    const labels: Record<string, string> = {
        grant: __('Grant mode'),
        draft: __('Draft mode'),
        none: __('No approval'),
    };

    return labels[mode] ?? mode;
}

function modeBadgeClass(mode: ManagedApprovalRule['mode']): string {
    const classes: Record<string, string> = {
        grant: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        draft: 'border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300',
        none: 'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400',
    };

    return classes[mode] ?? classes.none;
}

watch(
    () => props.rule?.source_key,
    () => {
        const rule = props.rule;

        isHydrating.value = true;
        errors.value = {};

        if (rule) {
            const steps: ApprovalRuleStepFormData[] =
                rule.steps && rule.steps.length > 0
                    ? rule.steps.map((s) => ({
                          label: s.label ?? '',
                          role_id: s.role_id,
                          order: s.order,
                      }))
                    : [
                          {
                              label: rule.step_label ?? '',
                              role_id: null,
                              order: 1,
                          },
                      ];

            form.value = {
                workflow_type: rule.workflow_type,
                steps,
                grant_validity_minutes: rule.grant_validity_minutes,
                reminder_after_minutes: rule.reminder_after_minutes,
                escalation_after_minutes: rule.escalation_after_minutes,
            };
        } else {
            form.value = defaultForm();
        }

        void nextTick(() => {
            isHydrating.value = false;
        });
    },
    { immediate: true },
);

watch(
    () => form.value.workflow_type,
    (type) => {
        if (isHydrating.value) {
            return;
        }
        if (type === 'single') {
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
    form.value.steps.forEach((s, i) => {
        s.order = i + 1;
    });
}

function close() {
    emit('update:open', false);
}

function submit() {
    if (!props.rule?.id || isPendingSync.value || isRemoved.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    router.patch(update.url({ approvalRule: props.rule.id }), form.value, {
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
                <DialogTitle>{{ __('Edit workflow settings') }}</DialogTitle>
                <DialogDescription>
                    {{
                        __(
                            'Capability and mode are config-managed and read-only. Adjust runtime workflow behaviour below.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- Pending sync / removed gate -->
            <div
                v-if="isPendingSync || isRemoved"
                class="flex items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-50/40 px-4 py-3.5 dark:bg-amber-500/[0.06]"
            >
                <Info
                    class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="space-y-1">
                    <p
                        class="text-sm font-medium text-amber-800 dark:text-amber-300"
                    >
                        {{
                            isRemoved
                                ? __('Capability retired')
                                : __('Sync required before editing')
                        }}
                    </p>
                    <p class="text-xs text-amber-700/80 dark:text-amber-400/70">
                        {{
                            isRemoved
                                ? __(
                                      'This approval capability has been removed from config and can no longer be edited from the UI.',
                                  )
                                : __(
                                      'This capability is pending sync. Run a sync from the status card above before editing workflow settings.',
                                  )
                        }}
                    </p>
                </div>
            </div>

            <template v-else-if="props.rule">
                <form
                    class="max-h-[65vh] space-y-4 overflow-y-auto pr-1"
                    @submit.prevent="submit"
                >
                    <!-- Read-only capability info -->
                    <div
                        class="space-y-2 rounded-xl border border-sidebar-border/60 bg-sidebar/20 px-4 py-3"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <p class="text-sm font-medium text-foreground">
                                {{ props.rule.action_label }}
                            </p>
                            <Badge
                                variant="outline"
                                class="rounded-full border-violet-500/20 bg-violet-500/10 text-xs text-violet-700 dark:text-violet-300"
                            >
                                <Settings2 class="mr-1 size-2.5" />
                                {{ __('Config-managed') }}
                            </Badge>
                            <Badge
                                variant="outline"
                                class="rounded-full text-xs"
                                :class="modeBadgeClass(props.rule.mode)"
                            >
                                {{ modeLabel(props.rule.mode) }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <Badge
                                variant="outline"
                                class="rounded-full text-xs"
                            >
                                {{ props.rule.app_label }}
                            </Badge>
                            <Badge
                                variant="secondary"
                                class="rounded-full text-xs"
                            >
                                {{ props.rule.resource_label }}
                            </Badge>
                        </div>
                        <p
                            class="font-mono text-[10px] text-muted-foreground/60"
                        >
                            {{ props.rule.permission_name }}
                        </p>
                    </div>

                    <Separator />

                    <!-- Enable / disable toggle -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="space-y-0.5">
                            <p class="text-sm font-medium text-foreground">
                                {{ __('Enable rule') }}
                            </p>
                            <p
                                v-if="!props.rule.toggleable"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    __(
                                        'Managed by config — cannot be toggled from the UI.',
                                    )
                                }}
                            </p>
                            <p
                                v-else-if="
                                    !props.rule.enabled && !props.rule.is_ready
                                "
                                class="text-xs text-amber-700 dark:text-amber-300"
                            >
                                {{
                                    props.rule.readiness_issues[0] ??
                                    __(
                                        'Complete workflow settings before enabling.',
                                    )
                                }}
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                {{
                                    props.rule.enabled
                                        ? __(
                                              'Rule is active and will intercept matching actions.',
                                          )
                                        : __(
                                              'Rule is inactive. Enable to start intercepting actions.',
                                          )
                                }}
                            </p>
                        </div>

                        <span
                            v-if="!props.rule.toggleable"
                            class="flex h-5 w-9 shrink-0 items-center justify-center"
                        >
                            <Info
                                class="size-3.5 text-muted-foreground/40"
                                :title="__('Managed by config — cannot toggle')"
                            />
                        </span>
                        <span
                            v-else-if="
                                !props.rule.enabled && !props.rule.is_ready
                            "
                            class="flex h-5 w-9 shrink-0 items-center justify-center"
                        >
                            <Info
                                class="size-3.5 text-amber-600/70 dark:text-amber-300/70"
                            />
                        </span>
                        <button
                            v-else
                            type="button"
                            role="switch"
                            :aria-checked="props.rule.enabled"
                            :aria-label="
                                props.rule.enabled
                                    ? __('Disable :action', {
                                          action: props.rule.action_label,
                                      })
                                    : __('Enable :action', {
                                          action: props.rule.action_label,
                                      })
                            "
                            :disabled="
                                !canToggle || togglingEnabled || processing
                            "
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                props.rule.enabled ? 'bg-primary' : 'bg-input'
                            "
                            @click="doToggle"
                        >
                            <span
                                class="pointer-events-none flex size-4 items-center justify-center rounded-full bg-background shadow-lg ring-0 transition-transform"
                                :class="
                                    props.rule.enabled
                                        ? 'translate-x-4'
                                        : 'translate-x-0'
                                "
                            >
                                <Spinner
                                    v-if="togglingEnabled"
                                    class="size-2.5"
                                />
                            </span>
                        </button>
                    </div>

                    <Separator />

                    <!-- Workflow type -->
                    <div class="grid gap-1.5">
                        <Label
                            class="text-xs font-medium tracking-wide uppercase"
                        >
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
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        {{ __('Single step') }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            __('One reviewer role per request.')
                                        }}
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
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
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
                                <GripVertical
                                    v-if="form.workflow_type === 'multi'"
                                    class="mt-2 size-3.5 shrink-0 text-muted-foreground/40"
                                />

                                <div class="flex-1 space-y-2">
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        {{
                                            __('Step :n', {
                                                n: String(index + 1),
                                            })
                                        }}
                                    </p>

                                    <Input
                                        v-if="form.workflow_type === 'multi'"
                                        :id="`step-label-${index}`"
                                        v-model="step.label"
                                        :placeholder="
                                            __('Step label (optional)')
                                        "
                                        class="h-8 text-sm"
                                    />

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
                                                            n: String(
                                                                role.level,
                                                            ),
                                                        })
                                                    }})
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormFieldSupport
                                        :error="
                                            errors[`steps.${index}.role_id`]
                                        "
                                    />
                                </div>

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
                        <Label
                            class="text-xs font-medium tracking-wide uppercase"
                        >
                            {{ __('Timing') }}
                        </Label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label
                                    for="managed-reminder-minutes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ __('Reminder after (minutes)') }}
                                </Label>
                                <Input
                                    id="managed-reminder-minutes"
                                    :model-value="
                                        form.reminder_after_minutes !== null
                                            ? String(
                                                  form.reminder_after_minutes,
                                              )
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
                                    :hint="
                                        __('Leave blank to disable reminders.')
                                    "
                                    :error="errors.reminder_after_minutes"
                                />
                            </div>

                            <div class="grid gap-1.5">
                                <Label
                                    for="managed-escalation-minutes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ __('Escalate after (minutes)') }}
                                </Label>
                                <Input
                                    id="managed-escalation-minutes"
                                    :model-value="
                                        form.escalation_after_minutes !== null
                                            ? String(
                                                  form.escalation_after_minutes,
                                              )
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
                                    :hint="
                                        __('Leave blank to disable escalation.')
                                    "
                                    :error="errors.escalation_after_minutes"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="managed-grant-validity-minutes"
                                class="text-xs text-muted-foreground"
                            >
                                {{ __('Grant validity (minutes)') }}
                            </Label>
                            <Input
                                id="managed-grant-validity-minutes"
                                :model-value="
                                    form.grant_validity_minutes !== null
                                        ? String(form.grant_validity_minutes)
                                        : ''
                                "
                                type="number"
                                min="1"
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
                </form>
            </template>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="close"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    :disabled="
                        processing || isPendingSync || isRemoved || !props.rule
                    "
                    @click="submit"
                >
                    <Spinner v-if="processing" />
                    {{ __('Save changes') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
