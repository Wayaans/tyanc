<?php

declare(strict_types=1);

it('uses explicit div click handlers instead of label wrappers for row interaction', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RolePermissionActionChecklist.vue'));

    // Rows must be divs with explicit @click handlers, not passive label wrappers
    expect($component)
        ->toContain('@click="handleToggleAll"')
        ->toContain('@click="handleToggle(action.permission)"')
        ->not->toContain('<label');
});

it('binds the checklist checkboxes with the shared checkbox model contract', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RolePermissionActionChecklist.vue'));

    expect($component)
        ->toContain('@update:model-value="(v) => toggleAll(Boolean(v))"')
        ->toContain('@update:model-value="(v) => toggle(action.permission, Boolean(v))"')
        ->toContain(':model-value="allSelected ? true : someSelected ? \'indeterminate\' : false"')
        ->toContain(':model-value="props.modelValue.includes(action.permission)"');
});

it('exposes correct aria attributes for accessibility on checklist rows', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RolePermissionActionChecklist.vue'));

    expect($component)
        ->toContain('role="checkbox"')
        ->toContain(':aria-checked="')
        ->toContain('tabindex="0"')
        ->toContain('@keydown="');
});
