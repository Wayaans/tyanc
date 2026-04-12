<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookCheck,
    ClipboardList,
    FileBarChart2,
    LayoutList,
    PackageCheck,
    RotateCcw,
    ShieldCheck,
    TimerOff,
} from 'lucide-vue-next';
import { computed } from 'vue';
import ApprovalStatusBadge from '@/components/cumpu/approvals/ApprovalStatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslations } from '@/lib/translations';
import { index as approvalRulesRoute } from '@/routes/cumpu/approval-rules';
import {
    all as approvalsAllRoute,
    index as approvalsInboxRoute,
    myRequests as approvalsMyRequestsRoute,
} from '@/routes/cumpu/approvals';
import { index as approvalsReportsRoute } from '@/routes/cumpu/approvals/reports';
import type { ApprovalRequestRow } from '@/types';
import type {
    CumpuDashboardAbilities,
    CumpuDashboardSummary,
} from '@/types/cumpu';

const props = defineProps<{
    summary: CumpuDashboardSummary;
    abilities: CumpuDashboardAbilities;
    recentInbox?: ApprovalRequestRow[];
    recentMyRequests?: ApprovalRequestRow[];
}>();

const { __ } = useTranslations();
const { cumpuDashboardBreadcrumbs } = useAppNavigation();

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

const coreStats = computed(() => [
    {
        key: 'inbox',
        title: __('Pending inbox'),
        value: props.summary.pending_inbox_count,
        description: __('Awaiting your review'),
        icon: BookCheck,
        href: props.abilities.viewInbox ? approvalsInboxRoute() : null,
        linkLabel: __('Open inbox'),
        highlight: props.summary.pending_inbox_count > 0,
    },
    {
        key: 'my_requests',
        title: __('My requests'),
        value: props.summary.my_request_count,
        description: __('Submitted by you'),
        icon: ClipboardList,
        href: props.abilities.viewMyRequests
            ? approvalsMyRequestsRoute()
            : null,
        linkLabel: __('View requests'),
        highlight: false,
    },
    {
        key: 'all_pending',
        title: __('All pending'),
        value: props.summary.all_pending_count,
        description: __('Across all workflows'),
        icon: LayoutList,
        href: props.abilities.viewAll ? approvalsAllRoute() : null,
        linkLabel: __('View all'),
        highlight: false,
    },
    {
        key: 'rules',
        title: __('Active rules'),
        value: props.summary.enabled_rule_count,
        description: __('Enforcement rules'),
        icon: ShieldCheck,
        href: props.abilities.manageRules ? approvalRulesRoute() : null,
        linkLabel: __('Manage rules'),
        highlight: false,
    },
]);

const grantStats = computed(() => [
    {
        key: 'ready_to_retry',
        title: __('Ready to retry'),
        value: props.summary.ready_to_retry_count,
        description: __('Grant issued — action ready'),
        icon: RotateCcw,
        href: props.abilities.viewMyRequests
            ? approvalsMyRequestsRoute()
            : null,
        linkLabel: __('View requests'),
        colorClass: 'text-emerald-600 dark:text-emerald-400',
        bgClass: 'bg-emerald-500/10',
        ringClass:
            props.summary.ready_to_retry_count > 0
                ? 'ring-1 ring-emerald-500/30'
                : '',
    },
    {
        key: 'consumed',
        title: __('Consumed'),
        value: props.summary.consumed_count,
        description: __('Grant used successfully'),
        icon: PackageCheck,
        href: props.abilities.viewAll ? approvalsAllRoute() : null,
        linkLabel: __('View all'),
        colorClass: 'text-violet-600 dark:text-violet-400',
        bgClass: 'bg-violet-500/10',
        ringClass: '',
    },
    {
        key: 'expired',
        title: __('Expired'),
        value: props.summary.expired_count,
        description: __('Grant unused — expired'),
        icon: TimerOff,
        href: props.abilities.viewAll ? approvalsAllRoute() : null,
        linkLabel: __('View all'),
        colorClass: 'text-amber-600 dark:text-amber-400',
        bgClass: 'bg-amber-500/10',
        ringClass:
            props.summary.expired_count > 0 ? 'ring-1 ring-amber-500/30' : '',
    },
]);

const hasRecentInbox = computed(() => (props.recentInbox?.length ?? 0) > 0);
const hasRecentMyRequests = computed(
    () => (props.recentMyRequests?.length ?? 0) > 0,
);
const showRecentSection = computed(
    () => hasRecentInbox.value || hasRecentMyRequests.value,
);
</script>

<template>
    <Head :title="__('Cumpu')" />

    <AppLayout :breadcrumbs="cumpuDashboardBreadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:gap-8">
            <!-- Hero -->
            <section
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-sidebar/20"
            >
                <div class="px-6 py-6 md:px-8 md:py-8">
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                variant="outline"
                                class="rounded-full text-xs"
                            >
                                {{ __('Cumpu') }}
                            </Badge>
                            <Badge
                                variant="outline"
                                class="rounded-full text-xs"
                            >
                                {{ __('Approval workflows') }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <h1
                                class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                            >
                                {{ __('Approval centre') }}
                            </h1>
                            <p
                                class="max-w-2xl text-sm leading-relaxed text-muted-foreground"
                            >
                                {{
                                    __(
                                        'Review pending requests, track submissions you have raised, and configure which actions require sign-off.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-1">
                            <Button
                                v-if="props.abilities.viewInbox"
                                size="sm"
                                as-child
                            >
                                <TextLink :href="approvalsInboxRoute()">
                                    <BookCheck class="mr-1.5 size-3.5" />
                                    {{ __('Open inbox') }}
                                </TextLink>
                            </Button>
                            <Button
                                v-if="props.abilities.viewMyRequests"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <TextLink :href="approvalsMyRequestsRoute()">
                                    <ClipboardList class="mr-1.5 size-3.5" />
                                    {{ __('My requests') }}
                                </TextLink>
                            </Button>
                            <Button
                                v-if="props.abilities.manageRules"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <TextLink :href="approvalRulesRoute()">
                                    <ShieldCheck class="mr-1.5 size-3.5" />
                                    {{ __('Approval rules') }}
                                </TextLink>
                            </Button>
                            <Button
                                v-if="props.abilities.viewAll"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <TextLink :href="approvalsAllRoute()">
                                    <LayoutList class="mr-1.5 size-3.5" />
                                    {{ __('All approvals') }}
                                </TextLink>
                            </Button>
                            <Button
                                v-if="props.abilities.viewReports"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <TextLink :href="approvalsReportsRoute()">
                                    <FileBarChart2 class="mr-1.5 size-3.5" />
                                    {{ __('Reports') }}
                                </TextLink>
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Core stats -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card
                    v-for="stat in coreStats"
                    :key="stat.key"
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                    :class="stat.highlight ? 'ring-1 ring-primary/20' : ''"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            {{ stat.title }}
                        </CardTitle>
                        <component
                            :is="stat.icon"
                            class="size-4"
                            :class="
                                stat.highlight
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                        />
                    </CardHeader>
                    <CardContent class="space-y-1">
                        <p
                            class="text-2xl font-semibold tracking-tight tabular-nums"
                            :class="
                                stat.highlight
                                    ? 'text-primary'
                                    : 'text-foreground'
                            "
                        >
                            {{ stat.value }}
                        </p>
                        <p class="text-sm leading-5 text-muted-foreground">
                            {{ stat.description }}
                        </p>
                        <TextLink
                            v-if="stat.href"
                            :href="stat.href"
                            class="inline-block pt-1 text-xs text-primary hover:underline"
                        >
                            {{ stat.linkLabel }} →
                        </TextLink>
                    </CardContent>
                </Card>
            </section>

            <!-- Grant lifecycle -->
            <section>
                <div class="mb-3 flex items-center gap-3 px-0.5">
                    <h2 class="shrink-0 text-sm font-semibold text-foreground">
                        {{ __('Grant lifecycle') }}
                    </h2>
                    <Separator class="flex-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <Card
                        v-for="stat in grantStats"
                        :key="stat.key"
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                        :class="stat.ringClass"
                    >
                        <CardHeader
                            class="flex flex-row items-start justify-between space-y-0 pb-2"
                        >
                            <CardTitle
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ stat.title }}
                            </CardTitle>
                            <div
                                class="flex size-7 items-center justify-center rounded-lg"
                                :class="stat.bgClass"
                            >
                                <component
                                    :is="stat.icon"
                                    class="size-3.5"
                                    :class="stat.colorClass"
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-1">
                            <p
                                class="text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                            >
                                {{ stat.value }}
                            </p>
                            <p class="text-sm leading-5 text-muted-foreground">
                                {{ stat.description }}
                            </p>
                            <TextLink
                                v-if="stat.href"
                                :href="stat.href"
                                class="inline-block pt-1 text-xs text-primary hover:underline"
                            >
                                {{ stat.linkLabel }} →
                            </TextLink>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Recent activity -->
            <section v-if="showRecentSection">
                <div class="mb-3 flex items-center gap-3 px-0.5">
                    <h2 class="shrink-0 text-sm font-semibold text-foreground">
                        {{ __('Recent activity') }}
                    </h2>
                    <Separator class="flex-1" />
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <!-- Recent inbox -->
                    <Card
                        v-if="hasRecentInbox"
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-2 text-sm font-semibold text-foreground"
                                >
                                    <BookCheck
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ __('Inbox') }}
                                </CardTitle>
                                <TextLink
                                    v-if="props.abilities.viewInbox"
                                    :href="approvalsInboxRoute()"
                                    class="text-xs text-primary hover:underline"
                                >
                                    {{ __('View all') }} →
                                </TextLink>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-0">
                            <ul class="divide-y divide-border/50">
                                <li
                                    v-for="req in recentInbox"
                                    :key="req.id"
                                    class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ req.subject_name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ req.action_label
                                            }}<span
                                                v-if="req.requested_by_name"
                                                aria-hidden="true"
                                            >
                                                · </span
                                            >{{ req.requested_by_name }}
                                        </p>
                                    </div>
                                    <ApprovalStatusBadge :status="req.status" />
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- Recent my requests -->
                    <Card
                        v-if="hasRecentMyRequests"
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-2 text-sm font-semibold text-foreground"
                                >
                                    <ClipboardList
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ __('My requests') }}
                                </CardTitle>
                                <TextLink
                                    v-if="props.abilities.viewMyRequests"
                                    :href="approvalsMyRequestsRoute()"
                                    class="text-xs text-primary hover:underline"
                                >
                                    {{ __('View all') }} →
                                </TextLink>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-0">
                            <ul class="divide-y divide-border/50">
                                <li
                                    v-for="req in recentMyRequests"
                                    :key="req.id"
                                    class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ req.subject_name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ req.action_label
                                            }}<span
                                                v-if="req.requested_at"
                                                aria-hidden="true"
                                            >
                                                · </span
                                            >{{
                                                dateFormatter.format(
                                                    new Date(req.requested_at),
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <ApprovalStatusBadge :status="req.status" />
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
