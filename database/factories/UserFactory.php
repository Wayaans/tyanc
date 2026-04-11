<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => Str::of((string) fake()->unique()->userName())
                ->lower()
                ->replaceMatches('/[^a-z0-9_-]+/', '-')
                ->trim('-_')
                ->value(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'avatar' => null,
            'status' => UserStatus::Active,
            'timezone' => 'UTC',
            'locale' => 'en',
            'email_verified_at' => now(),
            'remember_token' => fake()->regexify('[A-Za-z0-9]{10}'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'deleted_at' => null,
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutTwoFactor(): self
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}
