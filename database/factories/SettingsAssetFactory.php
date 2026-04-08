<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SettingsAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettingsAsset>
 */
final class SettingsAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
        ];
    }
}
