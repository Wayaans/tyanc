<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FileLibrary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FileLibrary>
 */
final class FileLibraryFactory extends Factory
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
            'is_system' => false,
        ];
    }

    public function system(): self
    {
        return $this->state(fn (): array => [
            'is_system' => true,
        ]);
    }
}
