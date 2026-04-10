<?php

declare(strict_types=1);

it('uses a non-empty sentinel value for clearing access matrix filters', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/access/AccessMatrixFilterBar.vue'));

    expect($component)
        ->toContain("const ALL_OPTION = '__all__';")
        ->toContain(':value="ALL_OPTION"')
        ->toContain("raw === ALL_OPTION ? '' : raw")
        ->not->toContain('<SelectItem value="">{{ __(\'All roles\') }}</SelectItem>')
        ->not->toContain('<SelectItem value="">{{ __(\'All apps\') }}</SelectItem>');
});
