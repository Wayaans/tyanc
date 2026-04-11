<?php

declare(strict_types=1);

it('renders the tyanc welcome page with translated landing copy', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('canRegister', true)
            ->where('translations', fn ($translations): bool => ($translations['The admin foundation for modern apps'] ?? null) === 'The admin foundation for modern apps'
                && ($translations['Manage users, roles, permissions, and app access across your platform.'] ?? null) === 'Manage users, roles, permissions, and app access across your platform.'
                && ($translations['Go to dashboard'] ?? null) === 'Go to dashboard'));
});
