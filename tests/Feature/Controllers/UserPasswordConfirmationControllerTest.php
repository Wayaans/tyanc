<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('flashes a toast when confirming the password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)
        ->withSession([
            'url.intended' => route('settings.account.edit'),
        ])
        ->from(route('password.confirm'))
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('settings.account.edit')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['variant'] === 'success'
            && $toast['message'] === 'Password confirmed.');

    $this->actingAs($user)
        ->get(route('settings.account.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/Account')
            ->where('flash.toast.variant', 'success')
            ->where('flash.toast.message', 'Password confirmed.'));
});
