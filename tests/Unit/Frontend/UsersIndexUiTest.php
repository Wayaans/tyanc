<?php

declare(strict_types=1);

it('removes the users import surface from the users index page', function (): void {
    $page = file_get_contents(resource_path('js/pages/tyanc/users/Index.vue'));

    expect($page)
        ->not->toContain('ImportSheet')
        ->not->toContain('@/components/tyanc/imports/ImportSheet.vue')
        ->not->toContain('props.abilities.import')
        ->not->toContain('props.features.imports_enabled')
        ->not->toContain("governed_actions?.['import']");
});

it('keeps export and create actions on the users index page', function (): void {
    $page = file_get_contents(resource_path('js/pages/tyanc/users/Index.vue'));

    expect($page)
        ->toContain('ExportMenu')
        ->toContain("{{ __('New user') }}");
});
