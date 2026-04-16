<?php

declare(strict_types=1);

it('keeps system default actions in the support row for preferences fields', function (): void {
    $page = file_get_contents(resource_path('js/pages/settings/Preferences.vue'));

    expect($page)->not->toContain('class="flex items-center justify-between"');

    foreach (['appearance', 'sidebar_variant', 'spacing_density', 'locale', 'timezone'] as $field) {
        $pattern = sprintf(
            '/<FormFieldSupport(?:(?!<\/FormFieldSupport>).)*clearField\(\'%s\'\)(?:(?!<\/FormFieldSupport>).)*<\/FormFieldSupport>/s',
            preg_quote($field, '/'),
        );

        expect((bool) preg_match($pattern, $page))->toBeTrue();
    }
});

it('passes locale and timezone errors into the shared support row', function (): void {
    $page = file_get_contents(resource_path('js/pages/settings/Preferences.vue'));

    expect($page)
        ->toContain(':error="errors.locale"')
        ->toContain(':error="errors.timezone"');
});

it('avoids duplicate timezone submission inputs on the preferences page', function (): void {
    $page = file_get_contents(resource_path('js/pages/settings/Preferences.vue'));

    expect((bool) preg_match('/<TimezoneCombobox(?:(?!\/>).)*name="timezone"/s', $page))->toBeFalse();
});
