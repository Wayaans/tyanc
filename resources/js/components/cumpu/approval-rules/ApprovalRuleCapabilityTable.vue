<script setup lang="ts">
import { Clock, Info, KeyRound, Pencil, Settings2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/translations';
import type { ManagedApprovalRule } from '@/types/cumpu';

const props = defineProps<{
    rules: ManagedApprovalRule[];
    canManage: boolean;
    isFiltered?: boolean;
}>();

const emit = defineEmits<{
    'edit-rule': [rule: ManagedApprovalRule];
}>();

const { __ } = useTranslations();

function syncStateBadgeClass(state: ManagedApprovalRule['sync_state']): string {
    const classes: Record<string, string> = {
        synced: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        incomplete:
            'border-orange-500/20 bg-orange-500/10 text-orange-700 dark:text-orange-300',
        pending_sync:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        removed:
            'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
        unknown:
            'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400',
    };

    return classes[state] ?? classes.unknown;
}

function syncStateLabel(state: ManagedApprovalRule['sync_state']): string {
    const labels: Record<string, string> = {
        synced: __('Synced'),
        incomplete: __('Needs setup'),
        pending_sync: __('Pending sync'),
        removed: __('Removed'),
        unknown: __('Unknown'),
    };

    return labels[state] ?? state;
}

function workflowSummary(rule: ManagedApprovalRule): string {
    if (rule.steps.length === 0) {
        return __('Not configured');
    }

    if (rule.workflow_type === 'multi' && rule.steps.length > 0) {
        return __(':n-step workflow', { n: String(rule.steps.length) });
    }

    return rule.step_label ?? __('Single-step workflow');
}

function timingSummary(rule: ManagedApprovalRule): string {
    const parts: string[] = [];

    if (rule.grant_validity_minutes) {
        parts.push(
            __('Grant :n min', { n: String(rule.grant_validity_minutes) }),
        );
    }

    if (rule.reminder_after_minutes) {
        parts.push(
            __('Reminder :n min', {
                n: String(rule.reminder_after_minutes),
            }),
        );
    }

    if (rule.escalation_after_minutes) {
        parts.push(
            __('Escalate :n min', {
                n: String(rule.escalation_after_minutes),
            }),
        );
    }

    return parts.join(' · ');
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

function readinessSummary(rule: ManagedApprovalRule): string {
    return (
        rule.readiness_issues[0] ??
        __('Complete the workflow settings before enabling this rule.')
    );
}
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
    >
        <!-- Header -->
        <div
            class="grid grid-cols-[minmax(0,2.5fr)_minmax(0,1.5fr)_minmax(0,1fr)_100px_44px] gap-3 border-b border-sidebar-border/50 px-5 py-2.5"
        >
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Capability / permission') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Workflow') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Timings') }}
            </p>
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ __('Sync state') }}
            </p>
            <p class="sr-only">
                {{ __('Edit') }}
            </p>
        </div>

        <!-- Empty state -->
        <div
            v-if="props.rules.length === 0"
            class="flex flex-col items-center gap-2 px-5 py-12 text-center"
        >
            <KeyRound class="size-8 text-muted-foreground/30" />
            <p class="text-sm font-medium text-foreground">
                {{
                    props.isFiltered
                        ? __('No rules match these filters.')
                        : __('No capabilities configured.')
                }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{
                    props.isFiltered
                        ? __('Try clearing one or more filters.')
                        : __(
                              'Run a sync to import capabilities from the approval source of truth.',
                          )
                }}
            </p>
        </div>

        <!-- Rows -->
        <div v-else class="divide-y divide-sidebar-border/40">
            <div
                v-for="rule in props.rules"
                :key="rule.source_key"
                class="grid grid-cols-[minmax(0,2.5fr)_minmax(0,1.5fr)_minmax(0,1fr)_100px_44px] items-center gap-3 px-5 py-3.5 transition-colors"
                :class="
                    rule.sync_state === 'removed'
                        ? 'opacity-50'
                        : 'hover:bg-sidebar/10'
                "
            >
                <!-- Capability info -->
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ rule.action_label }}
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
                            :class="modeBadgeClass(rule.mode)"
                        >
                            {{ modeLabel(rule.mode) }}
                        </Badge>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge variant="outline" class="rounded-full text-xs">
                            {{ rule.app_label }}
                        </Badge>
                        <Badge variant="secondary" class="rounded-full text-xs">
                            {{ rule.resource_label }}
                        </Badge>
                    </div>
                    <p class="font-mono text-[10px] text-muted-foreground/60">
                        {{ rule.permission_name }}
                    </p>
                </div>

                <!-- Workflow -->
                <div class="min-w-0 space-y-1">
                    <p class="text-sm text-foreground">
                        {{ workflowSummary(rule) }}
                    </p>
                    <div
                        v-if="
                            rule.workflow_type === 'multi' &&
                            rule.steps.length > 0
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
                                __('Step :n', { n: String(step.order) })
                            }}
                        </span>
                    </div>
                    <p
                        v-else-if="rule.step_role_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ rule.step_role_name }}
                    </p>
                    <p
                        v-if="
                            ['synced', 'incomplete'].includes(
                                rule.sync_state,
                            ) && !rule.is_ready
                        "
                        class="text-xs text-amber-700 dark:text-amber-300"
                    >
                        {{ readinessSummary(rule) }}
                    </p>
                </div>

                <!-- Timings -->
                <div class="min-w-0">
                    <p
                        v-if="timingSummary(rule)"
                        class="flex items-start gap-1 text-xs text-muted-foreground"
                    >
                        <Clock class="mt-0.5 size-3 shrink-0 opacity-60" />
                        <span class="leading-relaxed">
                            {{ timingSummary(rule) }}
                        </span>
                    </p>
                    <p v-else class="text-xs text-muted-foreground/40">
                        {{ __('—') }}
                    </p>
                </div>

                <!-- Sync state -->
                <div>
                    <Badge
                        variant="outline"
                        class="rounded-full text-xs"
                        :class="syncStateBadgeClass(rule.sync_state)"
                    >
                        {{ syncStateLabel(rule.sync_state) }}
                    </Badge>
                </div>

                <!-- Edit action -->
                <div class="flex items-center justify-center">
                    <!-- Pending sync: locked edit -->
                    <span
                        v-if="
                            rule.id === null ||
                            rule.sync_state === 'pending_sync'
                        "
                        class="flex size-7 items-center justify-center"
                        :title="__('Sync required before editing')"
                    >
                        <Info class="size-3.5 text-muted-foreground/30" />
                    </span>

                    <!-- Synced: editable -->
                    <Button
                        v-else-if="
                            props.canManage &&
                            ['synced', 'incomplete'].includes(rule.sync_state)
                        "
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 text-muted-foreground hover:text-foreground"
                        :aria-label="
                            __('Edit :action workflow', {
                                action: rule.action_label,
                            })
                        "
                        @click="emit('edit-rule', rule)"
                    >
                        <Pencil class="size-3.5" />
                    </Button>

                    <!-- Removed or no-manage: nothing -->
                    <span v-else class="size-7" />
                </div>
            </div>
        </div>
    </div>
</template>
