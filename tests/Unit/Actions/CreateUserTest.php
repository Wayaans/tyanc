<?php

declare(strict_types=1);

use App\Actions\CreateUser;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

it('may create a user and profile', function (): void {
    Event::fake([Registered::class]);

    $action = resolve(CreateUser::class);

    $user = $action->handle([
        'name' => 'Test User',
        'email' => 'example@email.com',
        'first_name' => 'Test',
        'last_name' => 'User',
    ], 'password');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Test User')
        ->and($user->username)->toBe('example')
        ->and($user->email)->toBe('example@email.com')
        ->and($user->password)->not->toBe('password')
        ->and($user->profile)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    Event::assertDispatched(Registered::class);
});

it('uses the provided username when available', function (): void {
    $action = resolve(CreateUser::class);

    $user = $action->handle([
        'username' => 'tyanc-admin',
        'email' => 'admin@example.com',
    ], 'password');

    expect($user->username)->toBe('tyanc-admin');
});
