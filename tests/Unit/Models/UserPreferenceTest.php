<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPreference;

it('belongs to a user and casts preference attributes', function (): void {
    $preference = UserPreference::factory()->for(User::factory(), 'user')->create([
        'locale' => 'id',
        'timezone' => 'Asia/Makassar',
        'appearance' => 'dark',
        'sidebar_variant' => 'floating',
        'spacing_density' => 'comfortable',
    ]);

    expect($preference->user)->toBeInstanceOf(User::class)
        ->and($preference->user_id)->toBeString()
        ->and($preference->locale)->toBe('id')
        ->and($preference->timezone)->toBe('Asia/Makassar')
        ->and($preference->appearance)->toBe('dark')
        ->and($preference->sidebar_variant)->toBe('floating')
        ->and($preference->spacing_density)->toBe('comfortable');
});
