<script setup lang="ts">
import { AlertTriangle, ArrowRightLeft, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { ApprovalRequestRow } from '@/types';

const props = defineProps<{
    row: Pick<
        ApprovalRequestRow,
        | 'pending_assignee_names'
        | 'current_step_label'
        | 'current_step_order'
        | 'is_reassigned'
        | 'is_escalated'
    >;
}>();

const { __ } = useTranslations();

const assigneeLabel = computed(() => {
    const names = props.row.pending_assignee_names ?? [];
    if (names.length === 0) {
        return null;
    }
    if (names.length === 1) {
        return names[0];
    }
    return `${names[0]} +${names.length - 1}`;
});

const stepLabel = computed(
    () =>
        props.row.current_step_label ??
        (props.row.current_step_order !== null
            ? `Step ${props.row.current_step_order}`
            : null),
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <!-- Step badge -->
        <Badge
            v-if="stepLabel"
            variant="secondary"
            class="rounded-full text-xs"
        >
            {{ stepLabel }}
        </Badge>

        <!-- Assignee label -->
        <span
            v-if="assigneeLabel"
            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
        >
            <Users class="size-3 shrink-0" />
            {{ assigneeLabel }}
        </span>

        <!-- Escalated flag -->
        <Badge
            v-if="props.row.is_escalated"
            variant="outline"
            class="rounded-full border-amber-500/30 bg-amber-500/10 text-xs text-amber-700 dark:text-amber-300"
        >
            <AlertTriangle class="mr-0.5 size-2.5" />
            {{ __('Escalated') }}
        </Badge>

        <!-- Reassigned flag -->
        <Badge
            v-if="props.row.is_reassigned"
            variant="outline"
            class="rounded-full border-violet-500/30 bg-violet-500/10 text-xs text-violet-700 dark:text-violet-300"
        >
            <ArrowRightLeft class="mr-0.5 size-2.5" />
            {{ __('Reassigned') }}
        </Badge>
    </div>
</template>
