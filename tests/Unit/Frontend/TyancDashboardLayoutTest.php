<?php

declare(strict_types=1);

function normalizedTyancDashboardSource(string $path): string
{
    return preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($path))) ?? '';
}

it('composes the tyanc dashboard from interactive real-data panels', function (): void {
    $dashboard = normalizedTyancDashboardSource('js/pages/tyanc/Dashboard.vue');
    $moduleCard = normalizedTyancDashboardSource('js/components/tyanc/dashboard/ModuleCard.vue');

    expect($dashboard)
        ->toContain("from '@/types/tyanc/dashboard'")
        ->toContain('TyancDashboardProps')
        ->toContain('<ModuleCard')
        ->toContain('<RecentUsersPanel')
        ->toContain('<PermissionsPanel')
        ->toContain('<RolesPanel')
        ->toContain('<FilesPanel')
        ->toContain('<AppsPanel')
        ->toContain('messagesUnreadCount')
        ->and($moduleCard)
        ->toContain("props.href ? Link : 'div'")
        ->toContain('hover:border-primary/30')
        ->toContain('hover:-translate-y-0.5');
});
