<script setup lang="ts">
import ApprovalListTable from '@/components/tyanc/approvals/ApprovalListTable.vue';
import { show } from '@/routes/cumpu/approvals';
import type { ApprovalRequestRow, DataTablePayload } from '@/types';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

type RouteFactory = (options?: RouteQueryOptions) => RouteDefinition<'get'>;

const props = withDefaults(
    defineProps<{
        approvalsTable: DataTablePayload<ApprovalRequestRow>;
        route: RouteFactory;
        only?: string[];
        emptyTitle?: string;
        emptyDescription?: string;
        showDetailLink?: boolean;
    }>(),
    {
        only: () => [],
        emptyTitle: undefined,
        emptyDescription: undefined,
        showDetailLink: false,
    },
);

const emit = defineEmits<{
    decide: [request: ApprovalRequestRow];
}>();

function detailHref(request: ApprovalRequestRow): string {
    return show.url({ approvalRequest: request.id });
}
</script>

<template>
    <ApprovalListTable
        :approvals-table="props.approvalsTable"
        :route="props.route"
        :only="props.only"
        :empty-title="props.emptyTitle"
        :empty-description="props.emptyDescription"
        :detail-href="props.showDetailLink ? detailHref : undefined"
        @decide="emit('decide', $event)"
    />
</template>
