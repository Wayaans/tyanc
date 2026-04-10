<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<App>
 */
final class AppFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = Str::of((string) fake()->unique()->slug(2))
            ->lower()
            ->replace('-', '_')
            ->value();

        return [
            'key' => $key,
            'label' => Str::of($key)->replace('_', ' ')->title()->value(),
            'route_prefix' => Str::of($key)->replace('_', '-')->value(),
            'icon' => fake()->randomElement(['layout-grid', 'flask-conical', 'settings', 'shield-check', 'user']),
            'permission_namespace' => $key,
            'enabled' => true,
            'sort_order' => fake()->numberBetween(10, 200),
            'is_system' => false,
        ];
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => [
            'enabled' => false,
        ]);
    }

    public function system(): self
    {
        return $this->state(fn (): array => [
            'enabled' => true,
            'is_system' => true,
        ]);
    }
}
