<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/translations';
import type { ApprovalStatus } from '@/types';

const props = defineProps<{
    status: ApprovalStatus;
    size?: 'xs' | 'sm';
}>();

const { __ } = useTranslations();

type StatusConfig = {
    label: string;
    badgeClass: string;
};

const statusConfigs: Record<ApprovalStatus, StatusConfig> = {
    pending: {
        label: 'Pending',
        badgeClass:
            'border-slate-500/20 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    },
    in_review: {
        label: 'In review',
        badgeClass:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
    approved: {
        label: 'Approved',
        badgeClass:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    rejected: {
        label: 'Rejected',
        badgeClass:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-400',
    },
    cancelled: {
        label: 'Cancelled',
        badgeClass:
            'border-orange-500/20 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    },
    expired: {
        label: 'Expired',
        badgeClass:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    consumed: {
        label: 'Consumed',
        badgeClass:
            'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    },
};

const config = statusConfigs[props.status] ?? statusConfigs.pending;
</script>

<template>
    <Badge
        variant="outline"
        :class="`rounded-full text-xs ${config.badgeClass}`"
    >
        {{ __(config.label) }}
    </Badge>
</template>
