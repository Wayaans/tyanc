<script setup lang="ts">
import { Ban, CheckCircle2, Clock, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { ApprovalAssignmentRow } from '@/types/cumpu';

const props = defineProps<{
    assignments: ApprovalAssignmentRow[];
}>();

const { __ } = useTranslations();

const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }),
);

type StepConfig = {
    label: string;
    icon: typeof Clock;
    iconClass: string;
    badgeClass: string;
    lineClass: string;
};

function resolveStepConfig(status: string): StepConfig {
    switch (status) {
        case 'completed':
            return {
                label: 'Completed',
                icon: CheckCircle2,
                iconClass: 'text-emerald-600 dark:text-emerald-400',
                badgeClass:
                    'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                lineClass: 'bg-emerald-200 dark:bg-emerald-800',
            };
        case 'cancelled':
            return {
                label: 'Cancelled',
                icon: Ban,
                iconClass: 'text-orange-500 dark:text-orange-400',
                badgeClass:
                    'border-orange-500/20 bg-orange-500/10 text-orange-700 dark:text-orange-300',
                lineClass: 'bg-orange-200 dark:bg-orange-800',
            };
        case 'pending':
            return {
                label: 'Pending',
                icon: Clock,
                iconClass: 'text-sky-500 dark:text-sky-400',
                badgeClass:
                    'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
                lineClass: 'bg-sky-200 dark:bg-sky-800',
            };
        default:
            return {
                label: 'Pending',
                icon: Clock,
                iconClass: 'text-muted-foreground',
                badgeClass:
                    'border-muted-foreground/20 bg-muted/40 text-muted-foreground',
                lineClass: 'bg-border',
            };
    }
}

const sortedAssignments = computed(() =>
    [...props.assignments].sort(
        (a, b) => (a.step_order ?? 0) - (b.step_order ?? 0),
    ),
);
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
    >
        <div
            class="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3"
        >
            <Users class="size-3.5 shrink-0 text-muted-foreground" />
            <h2 class="text-sm font-semibold text-foreground">
                {{ __('Workflow steps') }}
            </h2>
        </div>

        <div
            v-if="sortedAssignments.length === 0"
            class="flex flex-col items-center gap-2 py-10 text-center"
        >
            <Users class="size-7 text-muted-foreground/30" />
            <p class="text-sm text-muted-foreground">
                {{ __('No assignment steps found.') }}
            </p>
        </div>

        <ul v-else class="space-y-1 p-3">
            <li
                v-for="(assignment, index) in sortedAssignments"
                :key="assignment.id"
                class="relative flex items-start gap-3"
            >
                <!-- Connector line -->
                <div
                    v-if="index < sortedAssignments.length - 1"
                    :class="[
                        'absolute top-8 bottom-0 left-[15px] z-0 w-0.5',
                        resolveStepConfig(assignment.status).lineClass,
                    ]"
                />

                <!-- Step icon -->
                <div
                    :class="[
                        'relative z-10 mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-background bg-sidebar/40 shadow-sm',
                        resolveStepConfig(assignment.status).iconClass,
                    ]"
                >
                    <component
                        :is="resolveStepConfig(assignment.status).icon"
                        class="size-4"
                    />
                </div>

                <!-- Step content -->
                <div class="min-w-0 flex-1 space-y-1.5 pb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="assignment.step_label"
                            class="text-sm leading-none font-medium text-foreground"
                        >
                            {{ assignment.step_label }}
                        </span>
                        <span
                            v-else-if="assignment.step_order !== null"
                            class="text-sm leading-none font-medium text-foreground"
                        >
                            {{ __('Step') }} {{ assignment.step_order }}
                        </span>

                        <Badge
                            variant="outline"
                            :class="`rounded-full text-xs ${resolveStepConfig(assignment.status).badgeClass}`"
                        >
                            {{ __(resolveStepConfig(assignment.status).label) }}
                        </Badge>
                    </div>

                    <p
                        v-if="assignment.role_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ __('Role') }}:
                        <span class="font-medium text-foreground">{{
                            assignment.role_name
                        }}</span>
                    </p>

                    <p
                        v-if="assignment.assigned_to_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ __('Assigned to') }}:
                        <span class="font-medium text-foreground">{{
                            assignment.assigned_to_name
                        }}</span>
                    </p>

                    <p
                        v-if="assignment.completed_by_name"
                        class="text-xs text-muted-foreground"
                    >
                        {{ __('Completed by') }}:
                        <span class="font-medium text-foreground">{{
                            assignment.completed_by_name
                        }}</span>
                    </p>

                    <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                        <p class="text-xs text-muted-foreground">
                            {{ __('Assigned') }}:
                            {{
                                dateFormatter.format(
                                    new Date(assignment.assigned_at),
                                )
                            }}
                        </p>
                        <p
                            v-if="assignment.completed_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{ __('Completed') }}:
                            {{
                                dateFormatter.format(
                                    new Date(assignment.completed_at),
                                )
                            }}
                        </p>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
