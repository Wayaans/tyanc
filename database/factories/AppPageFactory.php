<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\App;
use App\Models\AppPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppPage>
 */
final class AppPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = Str::of((string) fake()->unique()->slug(3))
            ->lower()
            ->replace('-', '_')
            ->value();

        $label = Str::of($key)->replace('_', ' ')->title()->value();
        $routePrefix = Str::of($key)->replace('_', '-')->value();

        return [
            'app_id' => App::factory(),
            'key' => $key,
            'label' => $label,
            'route_name' => null,
            'path' => '/'.$routePrefix,
            'permission_name' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'enabled' => true,
            'is_navigation' => true,
            'is_system' => false,
        ];
    }

    public function protected(): self
    {
        return $this->state(fn (): array => [
            'is_system' => true,
        ]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => [
            'enabled' => false,
        ]);
    }
}
