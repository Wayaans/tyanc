<?php

declare(strict_types=1);

it('has the tyanc welcome page', function (): void {
    $page = visit('/');

    $page->assertSee('Tyanc')
        ->assertSee('The admin foundation for modern apps');
});
