<?php

declare(strict_types=1);

it('renders a resource sidebar and role-scoped permission editor layout', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/access/AccessMatrixEditor.vue'));

    expect($component)
        ->toContain("{{ __('Resources') }}")
        ->toContain("'Permissions for :role'")
        ->toContain('selectedResourceKey')
        ->toContain('row[`role_${props.role.id}`]')
        ->not->toContain('Grant all')
        ->not->toContain('Revoke all');
});

it('shows permission actions as toggleable chips with tooltips', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/access/AccessMatrixEditor.vue'));

    expect($component)
        ->toContain('<TooltipProvider :delay-duration="400">')
        ->toContain('@click="handleToggle(row)"')
        ->toContain('{{ row.permission }}')
        ->toContain('<Checkbox');
});
