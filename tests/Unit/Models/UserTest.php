<?php

declare(strict_types=1);

use App\Models\User;

it('exposes the expected array shape', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'username',
            'email',
            'avatar',
            'status',
            'timezone',
            'locale',
            'email_verified_at',
            'two_factor_confirmed_at',
            'last_login_at',
            'last_login_ip',
            'created_at',
            'updated_at',
            'deleted_at',
            'name',
        ]);
});
