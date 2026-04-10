<?php

declare(strict_types=1);

it('uses a non-empty placeholder sentinel for single-select access matrix filters', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/access/AccessMatrixFilterBar.vue'));

    expect($component)
        ->toContain("const UNSET = '__unset__';")
        ->toContain(':value="UNSET" disabled')
        ->toContain("raw === UNSET ? '' : raw")
        ->not->toContain('All roles')
        ->not->toContain('All apps');
});
