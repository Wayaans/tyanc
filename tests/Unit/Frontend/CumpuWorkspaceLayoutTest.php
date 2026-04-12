<?php

declare(strict_types=1);

function normalizedFrontendSource(string $path): string
{
    return preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($path))) ?? '';
}

it('keeps cumpu filter bars aligned with shared 9-height controls', function (): void {
    $overviewFilters = normalizedFrontendSource('js/components/cumpu/approvals/ApprovalOverviewFilters.vue');
    $reportsPage = normalizedFrontendSource('js/pages/cumpu/approvals/Reports.vue');

    expect($overviewFilters)
        ->toContain('rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3')
        ->toContain('class="overflow-x-auto"')
        ->toContain('class="flex items-center gap-2.5"')
        ->toContain('class="h-9 min-w-0 flex-1 text-sm"')
        ->toContain('class="h-9 w-44 shrink-0 text-sm"')
        ->toContain('class="h-9 w-32 shrink-0 text-sm"')
        ->toContain('class="mx-0.5 h-4 w-px shrink-0 bg-border"')
        ->toContain('class="flex shrink-0 cursor-pointer items-center gap-1.5"')
        ->toContain('class="ml-auto h-9 shrink-0 gap-1 text-xs text-muted-foreground hover:text-foreground"')
        ->not->toContain('space-y-2.5 rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3')
        ->and($reportsPage)
        ->toContain('rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3')
        ->toContain('class="overflow-x-auto"')
        ->toContain('class="flex items-center gap-2.5"')
        ->toContain('class="flex shrink-0 items-center gap-1.5"')
        ->toContain('class="h-9 w-36"')
        ->toContain('class="h-9 w-44 shrink-0 text-sm"')
        ->toContain('class="h-9 w-32 shrink-0 text-sm"')
        ->toContain('class="mx-0.5 h-4 w-px shrink-0 bg-border"')
        ->toContain('class="flex shrink-0 cursor-pointer items-center gap-1.5"')
        ->toContain('class="ml-auto h-9 shrink-0 gap-1 text-xs text-muted-foreground hover:text-foreground"')
        ->not->toContain('space-y-2.5 rounded-xl border border-sidebar-border/70 bg-background/80 px-4 py-3');
});

it('renders approval report summary cards from the latest approval statuses', function (): void {
    $summaryCards = normalizedFrontendSource('js/components/cumpu/approvals/reports/ApprovalReportSummaryCards.vue');

    expect($summaryCards)
        ->toContain("key: 'pending'")
        ->toContain("key: 'in_review'")
        ->toContain("key: 'approved'")
        ->toContain("key: 'consumed'")
        ->toContain("key: 'rejected'")
        ->toContain("key: 'cancelled'")
        ->toContain("key: 'expired'")
        ->not->toContain("key: 'pending_review'")
        ->not->toContain("key: 'overdue'")
        ->not->toContain("key: 'escalated'")
        ->not->toContain("key: 'reassigned'");
});

it('uses a padded and sectioned approval request drawer layout', function (): void {
    $drawer = normalizedFrontendSource('js/components/cumpu/approvals/ApprovalRequestDrawer.vue');

    expect($drawer)
        ->toContain('border-b border-sidebar-border/70 px-6 pt-6 pb-5')
        ->toContain('class="flex-1 space-y-4 overflow-y-auto px-6 py-5"')
        ->toContain('class="shrink-0 flex-row flex-wrap border-t border-sidebar-border/70 px-6 py-4"')
        ->toContain("{{ __('Grant lifecycle') }}")
        ->toContain("{{ __('Request reason') }}");
});

it('surfaces grant lifecycle and recent queue data on the cumpu dashboard', function (): void {
    $dashboard = normalizedFrontendSource('js/pages/cumpu/Dashboard.vue');

    expect($dashboard)
        ->toContain('ready_to_retry_count')
        ->toContain('consumed_count')
        ->toContain('expired_count')
        ->toContain('recentInbox')
        ->toContain('recentMyRequests')
        ->toContain("{{ __('Grant lifecycle') }}")
        ->toContain("{{ __('Recent activity') }}");
});
