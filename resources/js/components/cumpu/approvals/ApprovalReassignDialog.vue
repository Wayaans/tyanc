<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import { Button } from '@/components/ui/button';
import { ComboboxSelect } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';
import { reassign } from '@/routes/cumpu/approvals';
import type { ApprovalRequestRow } from '@/types';
import type { ReassignOption } from '@/types/cumpu';

const props = defineProps<{
    request: ApprovalRequestRow | null;
    reassignOptions: ReassignOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { __ } = useTranslations();

type StepSelection = {
    assignment_id: string;
    new_assignee_id: string;
};

const selections = ref<StepSelection[]>([]);
const note = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});

watch(
    [open, () => props.reassignOptions],
    ([isOpen]) => {
        if (!isOpen) {
            return;
        }
        // Initialise one entry per step that has eligible assignees
        selections.value = props.reassignOptions
            .filter((opt) => opt.eligible_assignees.length > 0)
            .map((opt) => ({
                assignment_id: opt.assignment_id,
                new_assignee_id: opt.assigned_to_id ?? '',
            }));
        note.value = '';
        errors.value = {};
        processing.value = false;
    },
    { immediate: true },
);

const pendingSteps = computed(() =>
    props.reassignOptions.filter((opt) => opt.eligible_assignees.length > 0),
);

function selectionFor(assignmentId: string): StepSelection | undefined {
    return selections.value.find((s) => s.assignment_id === assignmentId);
}

function updateSelection(assignmentId: string, newAssigneeId: string) {
    const existing = selections.value.find(
        (s) => s.assignment_id === assignmentId,
    );
    if (existing) {
        existing.new_assignee_id = newAssigneeId;
    }
}

const isValid = computed(() =>
    selections.value.every((s) => s.new_assignee_id !== ''),
);

function submit() {
    if (!props.request || !isValid.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    router.patch(
        reassign.url({ approvalRequest: props.request.id }),
        {
            assignments: selections.value,
            note: note.value || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onError: (responseErrors) => {
                errors.value = responseErrors as Record<string, string>;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ __('Reassign approval') }}</DialogTitle>
                <DialogDescription>
                    {{
                        __(
                            'Choose a new assignee for each pending step. Only eligible users are listed.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Request label -->
                <div
                    v-if="props.request"
                    class="rounded-xl border border-sidebar-border/70 bg-sidebar/20 px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ __('Request') }}
                    </p>
                    <p class="mt-0.5 text-sm font-medium text-foreground">
                        {{ props.request.subject_name }}
                    </p>
                </div>

                <!-- No pending steps -->
                <p
                    v-if="pendingSteps.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ __('No pending steps available for reassignment.') }}
                </p>

                <!-- Steps -->
                <div
                    v-for="(step, index) in pendingSteps"
                    :key="step.assignment_id"
                    class="space-y-1.5"
                >
                    <Separator v-if="index > 0" />

                    <Label
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{
                            step.step_label ??
                            (step.step_order !== null
                                ? `${__('Step')} ${step.step_order}`
                                : __('Step'))
                        }}
                    </Label>

                    <p
                        v-if="step.assigned_to_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ __('Currently') }}: {{ step.assigned_to_name }}
                    </p>

                    <ComboboxSelect
                        :options="step.eligible_assignees"
                        :model-value="
                            selectionFor(step.assignment_id)?.new_assignee_id ??
                            ''
                        "
                        :placeholder="__('Select new assignee…')"
                        :search-placeholder="__('Search users…')"
                        @update:model-value="
                            updateSelection(step.assignment_id, $event)
                        "
                    />

                    <FormFieldSupport
                        :error="
                            errors[`assignments.${index}.new_assignee_id`] ??
                            errors.assigned_to_id
                        "
                    />
                </div>

                <!-- Note -->
                <div v-if="pendingSteps.length > 0" class="space-y-1.5">
                    <Label class="text-sm">
                        {{ __('Note') }}
                        <span class="text-muted-foreground">
                            ({{ __('optional') }})
                        </span>
                    </Label>
                    <Textarea
                        v-model="note"
                        :placeholder="__('Reason for reassignment…')"
                        :rows="2"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="open = false"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    :disabled="
                        processing || !isValid || pendingSteps.length === 0
                    "
                    @click="submit"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? __('Saving…') : __('Reassign') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
