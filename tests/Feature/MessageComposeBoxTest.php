<?php

declare(strict_types=1);

it('keeps the compose box aligned to the send button and caps growth at 15 lines', function (): void {
    $component = file_get_contents(resource_path('js/components/tyanc/messages/ComposeBox.vue'));

    expect($component)
        ->not->toBeFalse()
        ->toContain('const MAX_LINES = 15;')
        ->toContain('const FALLBACK_LINE_HEIGHT_PX = 20;')
        ->toContain('const FALLBACK_MIN_HEIGHT_PX = 36;')
        ->toContain('Number.isNaN(parsedLineHeight)')
        ->toContain('sendButton.offsetHeight')
        ->toContain('Math.max(')
        ->toContain('Math.min(neededHeight, maxHeight)')
        ->toContain("textarea.style.overflowY = neededHeight > maxHeight ? 'auto' : 'hidden';")
        ->toContain(':rows="1"')
        ->toContain('ref="sendButton"');
});
