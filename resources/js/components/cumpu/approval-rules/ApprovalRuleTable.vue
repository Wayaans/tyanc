<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/translations';
import { destroy } from '@/routes/cumpu/approval-rules';
import type { ApprovalRule } from '@/types/cumpu';

const props = defineProps<{
    rules: ApprovalRule[];
}>();

const emit = defineEmits<{
    edit: [rule: ApprovalRule];
}>();

const { __ } = useTranslations();

const confirmDeleteRule = ref<ApprovalRule | null>(null);
const isDeleting = ref(false);

function openDeleteConfirm(rule: ApprovalRule) {
    confirmDeleteRule.value = rule;
}

function cancelDelete() {
    confirmDeleteRule.value = null;
}

function confirmDelete() {
    if (!confirmDeleteRule.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(destroy.url({ approvalRule: confirmDeleteRule.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            confirmDeleteRule.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-background/80 shadow-none"
    >
        <!-- Table header -->
        <div
            class="grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_80px_auto] gap-3 border-b border-sidebar-border/50 px-4 py-2.5"
        >
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Permission') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Reviewer role') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Workflow') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Enabled') }}
            </p>
            <span class="sr-only">{{ __('Actions') }}</span>
        </div>

        <!-- Empty state -->
        <div v-if="props.rules.length === 0" class="px-4 py-10 text-center">
            <p class="text-sm font-medium text-foreground">
                {{ __('No approval rules configured.') }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{
                    __('Add a rule to require approvals for sensitive actions.')
                }}
            </p>
        </div>

        <!-- Rows -->
        <div v-else class="divide-y divide-sidebar-border/40">
            <div
                v-for="rule in props.rules"
                :key="rule.id"
                class="grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_80px_auto] items-center gap-3 px-4 py-3 transition-colors hover:bg-sidebar/10"
            >
                <!-- Permission -->
                <div class="min-w-0 space-y-0.5">
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ rule.action_label }}
                    </p>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge variant="outline" class="rounded-full text-xs">
                            {{ rule.app_label }}
                        </Badge>
                        <Badge variant="secondary" class="rounded-full text-xs">
                            {{ rule.resource_label }}
                        </Badge>
                    </div>
                </div>

                <!-- Reviewer role -->
                <p class="truncate text-sm text-foreground">
                    {{
                        rule.workflow_type === 'multi'
                            ? __(':n roles', {
                                  n: String(rule.steps?.length ?? 0),
                              })
                            : (rule.step_role_name ?? __('—'))
                    }}
                </p>

                <!-- Workflow -->
                <div class="space-y-0.5">
                    <p class="text-sm text-muted-foreground">
                        {{
                            rule.workflow_type === 'multi'
                                ? __(':n steps', {
                                      n: String(rule.steps?.length ?? 0),
                                  })
                                : (rule.step_label ?? rule.workflow_type)
                        }}
                    </p>
                    <div
                        v-if="
                            rule.workflow_type === 'multi' && rule.steps?.length
                        "
                        class="flex flex-wrap gap-1"
                    >
                        <span
                            v-for="step in rule.steps"
                            :key="step.order"
                            class="rounded-full bg-sidebar/30 px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {{
                                step.label ||
                                step.role_name ||
                                `Step ${step.order}`
                            }}
                        </span>
                    </div>
                    <p
                        v-if="
                            rule.reminder_after_minutes ||
                            rule.escalation_after_minutes
                        "
                        class="text-xs text-muted-foreground"
                    >
                        <span v-if="rule.reminder_after_minutes">
                            {{
                                __('Reminder: :n min', {
                                    n: String(rule.reminder_after_minutes),
                                })
                            }}
                        </span>
                        <span
                            v-if="
                                rule.reminder_after_minutes &&
                                rule.escalation_after_minutes
                            "
                        >
                            ·
                        </span>
                        <span v-if="rule.escalation_after_minutes">
                            {{
                                __('Escalate: :n min', {
                                    n: String(rule.escalation_after_minutes),
                                })
                            }}
                        </span>
                    </p>
                    <p
                        v-if="rule.grant_validity_minutes"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        {{
                            __('Grant valid: :n min', {
                                n: String(rule.grant_validity_minutes),
                            })
                        }}
                    </p>
                </div>

                <!-- Enabled -->
                <div class="flex items-center">
                    <Checkbox
                        :model-value="rule.enabled"
                        disabled
                        :aria-label="
                            rule.enabled ? __('Enabled') : __('Disabled')
                        "
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 text-muted-foreground hover:text-foreground"
                        :aria-label="__('Edit rule')"
                        @click="emit('edit', rule)"
                    >
                        <Pencil class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 text-muted-foreground hover:text-destructive"
                        :aria-label="__('Delete rule')"
                        @click="openDeleteConfirm(rule)"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete confirmation dialog -->
    <Dialog
        :open="confirmDeleteRule !== null"
        @update:open="
            (v) => {
                if (!v) cancelDelete();
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ __('Delete approval rule?') }}</DialogTitle>
                <DialogDescription>
                    {{
                        __(
                            'This will remove the approval requirement for ":action" on ":resource". Actions will proceed without approval.',
                            {
                                action: confirmDeleteRule?.action_label ?? '',
                                resource:
                                    confirmDeleteRule?.resource_label ?? '',
                            },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="isDeleting"
                    @click="cancelDelete"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    variant="destructive"
                    :disabled="isDeleting"
                    @click="confirmDelete"
                >
                    <Spinner v-if="isDeleting" />
                    {{ __('Delete rule') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
