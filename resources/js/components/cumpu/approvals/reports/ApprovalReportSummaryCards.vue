<script setup lang="ts">
import {
    Ban,
    CheckCircle2,
    Clock,
    Eye,
    LayoutList,
    PackageCheck,
    Timer,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/lib/translations';
import type { ApprovalReportSummary } from '@/types';

const props = defineProps<{
    summary: ApprovalReportSummary;
}>();

const { __ } = useTranslations();

const cards = computed(() => [
    {
        key: 'total',
        label: __('Total'),
        value: props.summary.total,
        icon: LayoutList,
        iconClass: 'text-muted-foreground',
    },
    {
        key: 'pending',
        label: __('Pending'),
        value: props.summary.pending,
        icon: Clock,
        iconClass: 'text-amber-500',
    },
    {
        key: 'in_review',
        label: __('In review'),
        value: props.summary.in_review,
        icon: Eye,
        iconClass: 'text-sky-500',
    },
    {
        key: 'approved',
        label: __('Approved'),
        value: props.summary.approved,
        icon: CheckCircle2,
        iconClass: 'text-emerald-600',
    },
    {
        key: 'consumed',
        label: __('Consumed'),
        value: props.summary.consumed,
        icon: PackageCheck,
        iconClass: 'text-violet-500',
    },
    {
        key: 'rejected',
        label: __('Rejected'),
        value: props.summary.rejected,
        icon: XCircle,
        iconClass: 'text-red-500',
    },
    {
        key: 'cancelled',
        label: __('Cancelled'),
        value: props.summary.cancelled,
        icon: Ban,
        iconClass: 'text-slate-500',
    },
    {
        key: 'expired',
        label: __('Expired'),
        value: props.summary.expired,
        icon: Timer,
        iconClass: 'text-orange-500',
    },
]);
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
        <Card
            v-for="card in cards"
            :key="card.key"
            class="border-sidebar-border/70 bg-background/80 shadow-none"
        >
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 px-4 pt-3 pb-1"
            >
                <CardTitle class="text-xs font-medium text-muted-foreground">
                    {{ card.label }}
                </CardTitle>
                <component
                    :is="card.icon"
                    :class="['size-3.5 shrink-0', card.iconClass]"
                />
            </CardHeader>
            <CardContent class="px-4 pb-3">
                <p
                    class="text-xl font-semibold tracking-tight text-foreground tabular-nums"
                >
                    {{ card.value }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
