<?php

declare(strict_types=1);

it('constrains the messages workspace with the shared sidebar header height token', function (): void {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $header = file_get_contents(resource_path('js/components/AppSidebarHeader.vue'));
    $page = file_get_contents(resource_path('js/pages/tyanc/messages/Index.vue'));

    expect($layout)
        ->not->toBeFalse()
        ->toContain('[--app-sidebar-header-height:4rem]')
        ->toContain('group-has-data-[collapsible=icon]/sidebar-wrapper:[--app-sidebar-header-height:3rem]');

    expect($header)
        ->not->toBeFalse()
        ->toContain('h-[var(--app-sidebar-header-height)]');

    expect($page)
        ->not->toBeFalse()
        ->toContain('h-[calc(100svh-var(--app-sidebar-header-height,4rem))]');
});

it('always scrolls the open thread to the latest message when the message count grows', function (): void {
    $thread = file_get_contents(resource_path('js/components/tyanc/messages/MessageThread.vue'));

    expect($thread)
        ->not->toBeFalse()
        ->toContain('() => props.messages.length')
        ->toContain('void scrollToBottom(true);')
        ->not->toContain('lastMessage?.is_mine || isAtBottom.value');
});
