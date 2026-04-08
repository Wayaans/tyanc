<?php

declare(strict_types=1);

use App\Models\User;

it('redirects the legacy account appearance page to preferences', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertRedirect('/settings/preferences');
});
