<?php

declare(strict_types=1);

it('uses dropdown action menus for app and role datatables', function (): void {
    $appTableColumns = file_get_contents(resource_path('js/components/tyanc/apps/AppTableColumns.ts'));
    $roleTableColumns = file_get_contents(resource_path('js/components/tyanc/roles/RoleTableColumns.ts'));

    expect($appTableColumns)
        ->toContain('AppActionsDropdown')
        ->and($roleTableColumns)
        ->toContain('RoleActionsDropdown');
});

it('renders role permission summaries without the legacy permission count copy', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RolePermissionSummary.vue'));

    expect($component)
        ->toContain('&mdash;')
        ->not->toContain(':count permission(s)');
});

it('keeps access matrix toggles governed only by loading state in the role-scoped editor', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/access/AccessMatrixEditor.vue'));

    expect($component)
        ->toContain(':disabled="props.updating"')
        ->toContain("{{ __('Resources') }}")
        ->not->toContain('Grant all')
        ->not->toContain('Revoke all');
});
