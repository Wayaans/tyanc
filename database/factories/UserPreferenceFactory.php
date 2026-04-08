<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPreference>
 */
final class UserPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'locale' => fake()->randomElement(['en', 'id']),
            'timezone' => fake()->randomElement(['UTC', 'Asia/Makassar', 'Asia/Jakarta']),
            'appearance' => fake()->randomElement(['light', 'dark', 'system']),
            'sidebar_variant' => fake()->randomElement(['inset', 'sidebar', 'floating']),
            'spacing_density' => fake()->randomElement(['compact', 'default', 'comfortable']),
        ];
    }
}
