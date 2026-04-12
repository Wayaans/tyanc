<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookCheck,
    CheckCircle2,
    ClipboardList,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
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
    index as approvalsInboxRoute,
    myRequests as approvalsMyRequestsRoute,
} from '@/routes/cumpu/approvals';
import type {
    CumpuDashboardAbilities,
    CumpuDashboardSummary,
} from '@/types/cumpu';

const props = defineProps<{
    summary: CumpuDashboardSummary;
    abilities: CumpuDashboardAbilities;
}>();

const { __ } = useTranslations();
const { cumpuDashboardBreadcrumbs } = useAppNavigation();

const summaryCards = computed(() => [
    {
        key: 'inbox',
        title: __('Pending inbox'),
        value: props.summary.pending_inbox_count,
        description: __('Approval requests waiting for your review.'),
        icon: BookCheck,
        href: props.abilities.viewInbox ? approvalsInboxRoute() : null,
        linkLabel: __('View inbox'),
    },
    {
        key: 'my_requests',
        title: __('My requests'),
        value: props.summary.my_request_count,
        description: __('Requests you have submitted for approval.'),
        icon: ClipboardList,
        href: props.abilities.viewMyRequests
            ? approvalsMyRequestsRoute()
            : null,
        linkLabel: __('View my requests'),
    },
    {
        key: 'rules',
        title: __('Active rules'),
        value: props.summary.enabled_rule_count,
        description: __('Approval rules currently enforced across apps.'),
        icon: ShieldCheck,
        href: props.abilities.manageRules ? approvalRulesRoute() : null,
        linkLabel: __('Manage rules'),
    },
]);
</script>

<template>
    <Head :title="__('Cumpu')" />

    <AppLayout :breadcrumbs="cumpuDashboardBreadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:gap-6">
            <!-- Hero banner -->
            <section
                class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]"
            >
                <Card
                    class="border-sidebar-border/70 bg-sidebar/25 shadow-none"
                >
                    <CardContent class="space-y-4 px-5 py-5 md:px-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class="rounded-full">
                                {{ __('Cumpu') }}
                            </Badge>
                            <Badge variant="outline" class="rounded-full">
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
                                class="max-w-xl text-sm leading-6 text-muted-foreground"
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
                        </div>
                    </CardContent>
                </Card>

                <!-- Summary stat cards -->
                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
                    <Card
                        v-for="item in summaryCards"
                        :key="item.key"
                        class="border-sidebar-border/70 bg-background/80 shadow-none"
                    >
                        <CardHeader
                            class="flex flex-row items-start justify-between space-y-0 pb-2"
                        >
                            <CardTitle
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ item.title }}
                            </CardTitle>
                            <component
                                :is="item.icon"
                                class="size-4 text-muted-foreground"
                            />
                        </CardHeader>
                        <CardContent class="space-y-1">
                            <p
                                class="text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                            >
                                {{ item.value }}
                            </p>
                            <p class="text-sm leading-5 text-muted-foreground">
                                {{ item.description }}
                            </p>
                            <TextLink
                                v-if="item.href"
                                :href="item.href"
                                class="inline-block pt-1 text-xs text-primary hover:underline"
                            >
                                {{ item.linkLabel }} →
                            </TextLink>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <Separator />

            <!-- Quick access section (shown only for relevant abilities) -->
            <section class="space-y-3">
                <div class="space-y-1 px-1">
                    <h2 class="text-base font-semibold text-foreground">
                        {{ __('Quick access') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ __('Jump directly to the section you need.') }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Card
                        v-if="props.abilities.viewInbox"
                        class="group border-sidebar-border/70 bg-sidebar/10 shadow-none transition-colors hover:bg-sidebar/25"
                    >
                        <CardContent class="flex items-start gap-3 px-4 py-4">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <BookCheck class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <TextLink
                                    :href="approvalsInboxRoute()"
                                    class="text-sm font-medium text-foreground hover:underline"
                                >
                                    {{ __('Approvals inbox') }}
                                </TextLink>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{
                                        __(
                                            'Review requests assigned to you and make decisions.',
                                        )
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        v-if="props.abilities.viewMyRequests"
                        class="group border-sidebar-border/70 bg-sidebar/10 shadow-none transition-colors hover:bg-sidebar/25"
                    >
                        <CardContent class="flex items-start gap-3 px-4 py-4">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <ClipboardList class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <TextLink
                                    :href="approvalsMyRequestsRoute()"
                                    class="text-sm font-medium text-foreground hover:underline"
                                >
                                    {{ __('My requests') }}
                                </TextLink>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{
                                        __(
                                            'Track and cancel approval requests you have submitted.',
                                        )
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        v-if="props.abilities.manageRules"
                        class="group border-sidebar-border/70 bg-sidebar/10 shadow-none transition-colors hover:bg-sidebar/25"
                    >
                        <CardContent class="flex items-start gap-3 px-4 py-4">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <CheckCircle2 class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <TextLink
                                    :href="approvalRulesRoute()"
                                    class="text-sm font-medium text-foreground hover:underline"
                                >
                                    {{ __('Approval rules') }}
                                </TextLink>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{
                                        __(
                                            'Configure which actions require sign-off and who reviews them.',
                                        )
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
