<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserUpdateDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserUpdateDraft>
 */
final class UserUpdateDraftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'created_by_id' => User::factory(),
            'committed_by_id' => null,
            'revision' => 1,
            'payload' => [
                'name' => fake()->name(),
                'username' => fake()->unique()->userName(),
                'email' => fake()->unique()->safeEmail(),
                'status' => UserStatus::Active->value,
                'locale' => 'en',
                'timezone' => 'UTC',
                'roles' => [],
                'permissions' => [],
            ],
            'changed_fields' => ['name', 'email'],
            'committed_at' => null,
        ];
    }

    public function committed(): self
    {
        return $this->state(fn (): array => [
            'committed_by_id' => User::factory(),
            'committed_at' => now(),
        ]);
    }
}
