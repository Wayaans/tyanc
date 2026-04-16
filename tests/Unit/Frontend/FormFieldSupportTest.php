<?php

declare(strict_types=1);

it('does not reserve space when there is no error, hint, or support slot', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    expect($component)
        ->toContain('v-if="error || hint || $slots.default"')
        ->not->toContain('min-h-')
        ->not->toContain('data-state="empty"');
});

it('renders slot content with hint styling when no error or hint is present', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    expect($component)
        ->toContain('<div v-else class="text-xs leading-4 text-muted-foreground">')
        ->toContain('<slot />');
});

it('renders error state with the correct data-state attribute', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    expect($component)->toContain(":data-state=\"error ? 'error' : 'hint'\"");
});

it('keeps aria-live polite for screen reader announcements', function (): void {
    $component = file_get_contents(resource_path('js/components/FormFieldSupport.vue'));

    expect($component)->toContain('aria-live="polite"');
});
