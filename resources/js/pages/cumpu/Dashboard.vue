<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BookCheck,
    CheckCircle2,
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

const overdueCount = computed(() => props.summary.overdue_count ?? 0);
const hasPendingInbox = computed(() => props.summary.pending_inbox_count > 0);
const hasOverdue = computed(() => overdueCount.value > 0);

const operationalStatus = computed(() => {
    if (overdueCount.value > 0) {
        return {
            label: `${overdueCount.value} ${overdueCount.value === 1 ? __('request overdue') : __('requests overdue')}`,
            dotClass: 'bg-red-500',
            textClass: 'text-red-600 dark:text-red-400',
        };
    }

    if (props.summary.pending_inbox_count > 0) {
        return {
            label: `${props.summary.pending_inbox_count} ${props.summary.pending_inbox_count === 1 ? __('pending review') : __('pending review')}`,
            dotClass: 'bg-amber-500 animate-pulse',
            textClass: 'text-amber-600 dark:text-amber-400',
        };
    }

    return {
        label: __('No pending actions'),
        dotClass: 'bg-emerald-500',
        textClass: 'text-emerald-600 dark:text-emerald-400',
    };
});

const overdueRatio = computed(() => {
    const total = props.summary.all_pending_count;
    if (!total || !overdueCount.value) return 0;
    return Math.min(Math.round((overdueCount.value / total) * 100), 100);
});

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
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Command header -->
            <section
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex size-2 rounded-full"
                            :class="operationalStatus.dotClass"
                            aria-hidden="true"
                        />
                        <span
                            class="text-xs font-medium"
                            :class="operationalStatus.textClass"
                        >
                            {{ operationalStatus.label }}
                        </span>
                    </div>

                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                    >
                        {{ __('Approval centre') }}
                    </h1>

                    <p class="text-sm text-muted-foreground">
                        {{
                            __(
                                'Review pending requests, track submissions, and configure workflow enforcement rules.',
                            )
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap items-start gap-2">
                    <Button v-if="abilities.viewInbox" size="sm" as-child>
                        <TextLink :href="approvalsInboxRoute()">
                            <BookCheck class="mr-1.5 size-3.5" />
                            {{ __('Open inbox') }}
                        </TextLink>
                    </Button>
                    <Button
                        v-if="abilities.viewMyRequests"
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
                        v-if="abilities.viewAll"
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
                        v-if="abilities.viewReports"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <TextLink :href="approvalsReportsRoute()">
                            <FileBarChart2 class="mr-1.5 size-3.5" />
                            {{ __('Reports') }}
                        </TextLink>
                    </Button>
                    <Button
                        v-if="abilities.manageRules"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <TextLink :href="approvalRulesRoute()">
                            <ShieldCheck class="mr-1.5 size-3.5" />
                            {{ __('Rules') }}
                        </TextLink>
                    </Button>
                </div>
            </section>

            <!-- Overdue alert -->
            <div
                v-if="hasOverdue"
                class="flex items-start gap-3 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm"
                role="alert"
            >
                <AlertTriangle
                    class="mt-0.5 size-4 shrink-0 text-red-600 dark:text-red-400"
                    aria-hidden="true"
                />
                <p class="text-foreground">
                    <span class="font-semibold text-red-600 dark:text-red-400">
                        {{ overdueCount }}
                    </span>
                    {{
                        overdueCount === 1
                            ? __(
                                  'approval request is overdue and requires immediate attention.',
                              )
                            : __(
                                  'approval requests are overdue and require immediate attention.',
                              )
                    }}
                    <TextLink
                        v-if="abilities.viewAll"
                        :href="approvalsAllRoute()"
                        class="ml-1 font-medium text-red-600 underline-offset-2 hover:underline dark:text-red-400"
                    >
                        {{ __('Review now') }} →
                    </TextLink>
                </p>
            </div>

            <!-- Core operations stats -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <!-- Pending inbox -->
                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                    :class="hasPendingInbox ? 'ring-1 ring-primary/30' : ''"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ __('Pending inbox') }}
                        </CardTitle>
                        <div
                            class="flex size-7 items-center justify-center rounded-lg"
                            :class="
                                hasPendingInbox
                                    ? 'bg-primary/10'
                                    : 'bg-muted/50'
                            "
                        >
                            <BookCheck
                                class="size-3.5"
                                :class="
                                    hasPendingInbox
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p
                            class="text-4xl font-semibold tracking-tight tabular-nums"
                            :class="
                                hasPendingInbox
                                    ? 'text-primary'
                                    : 'text-foreground'
                            "
                        >
                            {{ summary.pending_inbox_count }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ __('Awaiting your review') }}
                        </p>
                        <TextLink
                            v-if="abilities.viewInbox"
                            :href="approvalsInboxRoute()"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            {{ __('Open inbox') }}
                            <ArrowRight class="size-3" aria-hidden="true" />
                        </TextLink>
                    </CardContent>
                </Card>

                <!-- My requests -->
                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ __('My requests') }}
                        </CardTitle>
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-muted/50"
                        >
                            <ClipboardList
                                class="size-3.5 text-muted-foreground"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p
                            class="text-4xl font-semibold tracking-tight text-foreground tabular-nums"
                        >
                            {{ summary.my_request_count }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ __('Submitted by you') }}
                        </p>
                        <TextLink
                            v-if="abilities.viewMyRequests"
                            :href="approvalsMyRequestsRoute()"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            {{ __('View requests') }}
                            <ArrowRight class="size-3" aria-hidden="true" />
                        </TextLink>
                    </CardContent>
                </Card>

                <!-- All pending -->
                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ __('All pending') }}
                        </CardTitle>
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-muted/50"
                        >
                            <LayoutList
                                class="size-3.5 text-muted-foreground"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            class="text-4xl font-semibold tracking-tight text-foreground tabular-nums"
                        >
                            {{ summary.all_pending_count }}
                        </p>
                        <div
                            v-if="summary.all_pending_count > 0"
                            class="space-y-1.5"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">
                                    {{ __('Overdue') }}
                                </span>
                                <span
                                    class="text-xs font-medium tabular-nums"
                                    :class="
                                        overdueCount > 0
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ overdueCount }}
                                </span>
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="
                                        overdueCount > 0
                                            ? 'bg-red-500'
                                            : 'bg-primary/40'
                                    "
                                    :style="`width: ${overdueCount > 0 ? overdueRatio : 100}%`"
                                />
                            </div>
                        </div>
                        <TextLink
                            v-if="abilities.viewAll"
                            :href="approvalsAllRoute()"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            {{ __('View all') }}
                            <ArrowRight class="size-3" aria-hidden="true" />
                        </TextLink>
                    </CardContent>
                </Card>

                <!-- Active rules -->
                <Card
                    class="border-sidebar-border/70 bg-background/80 shadow-none"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ __('Active rules') }}
                        </CardTitle>
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-muted/50"
                        >
                            <ShieldCheck
                                class="size-3.5 text-muted-foreground"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p
                            class="text-4xl font-semibold tracking-tight text-foreground tabular-nums"
                        >
                            {{ summary.enabled_rule_count }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ __('Enforcement rules enabled') }}
                        </p>
                        <TextLink
                            v-if="abilities.manageRules"
                            :href="approvalRulesRoute()"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            {{ __('Manage rules') }}
                            <ArrowRight class="size-3" aria-hidden="true" />
                        </TextLink>
                    </CardContent>
                </Card>
            </section>

            <!-- Grant lifecycle pipeline -->
            <section>
                <div class="mb-3 flex items-center gap-3 px-0.5">
                    <h2
                        class="shrink-0 text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ __('Grant lifecycle') }}
                    </h2>
                    <Separator class="flex-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- Ready to retry -->
                    <Card
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                        :class="
                            summary.ready_to_retry_count > 0
                                ? 'ring-1 ring-emerald-500/30'
                                : ''
                        "
                    >
                        <CardContent class="flex items-start gap-4 p-5">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10"
                            >
                                <RotateCcw
                                    class="size-4 text-emerald-600 dark:text-emerald-400"
                                />
                            </div>
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <p
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    {{ __('Ready to retry') }}
                                </p>
                                <p
                                    class="text-3xl font-semibold text-foreground tabular-nums"
                                >
                                    {{ summary.ready_to_retry_count }}
                                </p>
                                <p class="pt-0.5 text-xs text-muted-foreground">
                                    {{ __('Grant issued — action ready') }}
                                </p>
                                <TextLink
                                    v-if="abilities.viewMyRequests"
                                    :href="approvalsMyRequestsRoute()"
                                    class="inline-flex items-center gap-1 pt-1 text-xs text-emerald-600 hover:underline dark:text-emerald-400"
                                >
                                    {{ __('View requests') }}
                                    <ArrowRight
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                </TextLink>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Consumed -->
                    <Card
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                    >
                        <CardContent class="flex items-start gap-4 p-5">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10"
                            >
                                <PackageCheck
                                    class="size-4 text-violet-600 dark:text-violet-400"
                                />
                            </div>
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <p
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    {{ __('Consumed') }}
                                </p>
                                <p
                                    class="text-3xl font-semibold text-foreground tabular-nums"
                                >
                                    {{ summary.consumed_count }}
                                </p>
                                <p class="pt-0.5 text-xs text-muted-foreground">
                                    {{ __('Grant used successfully') }}
                                </p>
                                <TextLink
                                    v-if="abilities.viewAll"
                                    :href="approvalsAllRoute()"
                                    class="inline-flex items-center gap-1 pt-1 text-xs text-violet-600 hover:underline dark:text-violet-400"
                                >
                                    {{ __('View all') }}
                                    <ArrowRight
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                </TextLink>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Expired -->
                    <Card
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                        :class="
                            summary.expired_count > 0
                                ? 'ring-1 ring-amber-500/30'
                                : ''
                        "
                    >
                        <CardContent class="flex items-start gap-4 p-5">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10"
                            >
                                <TimerOff
                                    class="size-4 text-amber-600 dark:text-amber-400"
                                />
                            </div>
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <p
                                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                                >
                                    {{ __('Expired') }}
                                </p>
                                <p
                                    class="text-3xl font-semibold text-foreground tabular-nums"
                                >
                                    {{ summary.expired_count }}
                                </p>
                                <p class="pt-0.5 text-xs text-muted-foreground">
                                    {{ __('Grant unused — window closed') }}
                                </p>
                                <TextLink
                                    v-if="abilities.viewAll"
                                    :href="approvalsAllRoute()"
                                    class="inline-flex items-center gap-1 pt-1 text-xs text-amber-600 hover:underline dark:text-amber-400"
                                >
                                    {{ __('View all') }}
                                    <ArrowRight
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                </TextLink>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Recent activity -->
            <section v-if="showRecentSection">
                <div class="mb-3 flex items-center gap-3 px-0.5">
                    <h2
                        class="shrink-0 text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
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
                                    <Badge
                                        v-if="summary.pending_inbox_count > 0"
                                        class="rounded-full px-1.5 py-0 text-xs tabular-nums"
                                    >
                                        {{ summary.pending_inbox_count }}
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="rounded-full px-1.5 py-0 text-xs"
                                    >
                                        <CheckCircle2
                                            class="mr-0.5 size-3 text-emerald-500"
                                        />
                                        {{ __('Clear') }}
                                    </Badge>
                                </CardTitle>
                                <TextLink
                                    v-if="abilities.viewInbox"
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
                                    v-if="abilities.viewMyRequests"
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
