<?php

declare(strict_types=1);

it('does not reserve space when there is no error or hint', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    // The wrapper must be conditionally rendered so empty space is not reserved.
    expect($component)
        ->toContain('v-if="error || hint"')
        ->not->toContain('min-h-')
        ->not->toContain('data-state="empty"');
});

it('renders error state with the correct data-state attribute', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    expect($component)->toContain(":data-state=\"error ? 'error' : 'hint'\"");
});

it('keeps aria-live polite for screen reader announcements', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    expect($component)->toContain('aria-live="polite"');
});
