<?php

declare(strict_types=1);

it('uses shared field support for both role dialog fields', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RoleFormDialog.vue'));

    expect($component)
        ->toContain('<FormFieldSupport :error="errors.name" />')
        ->toContain(':error="errors.level"')
        ->not->toContain('<InputError :message="errors.name" />');
});

it('uses a single-column layout so the level hint does not break vertical rhythm', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RoleFormDialog.vue'));

    expect($component)
        ->toContain('class="grid gap-4"')
        ->not->toContain('sm:grid-cols-2');
});
