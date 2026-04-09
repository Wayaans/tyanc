<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPreference;

it('stores the guest locale in the session and shares translated auth props', function (): void {
    $this->from(route('login'))
        ->patch(route('locale.update'), [
            'locale' => 'id',
        ])
        ->assertRedirect(route('login'));

    $this->assertSame('id', session('locale'));

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->component('session/Create')
            ->where('locale', 'id')
            ->where('translations.Welcome back', 'Selamat datang kembali'));
});

it('prefers persisted user locale overrides on authenticated pages', function (): void {
    $user = User::factory()->create([
        'locale' => 'en',
        'timezone' => 'UTC',
    ]);

    UserPreference::factory()->for($user, 'user')->create([
        'locale' => 'id',
        'timezone' => 'Asia/Makassar',
        'appearance' => 'dark',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('settings.preferences.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Preferences')
            ->where('locale', 'id')
            ->where('preferences.locale', 'id')
            ->where('preferences.timezone', 'Asia/Makassar')
            ->where('preferences.resolved_locale', 'id')
            ->where('preferences.resolved_timezone', 'Asia/Makassar'));
});
