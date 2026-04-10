<?php

declare(strict_types=1);

it('uses shared field support for both role dialog fields', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/roles/RoleFormDialog.vue'));

    expect($component)
        ->toContain('<FormFieldSupport :error="errors.name" />')
        ->toContain(':error="errors.level"')
        ->not->toContain('<InputError :message="errors.name" />');
});
