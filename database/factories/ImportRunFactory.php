<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
final class ImportRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ImportRun::TypeUsers,
            'status' => ImportRun::StatusPendingApproval,
            'file_name' => 'users-import.xlsx',
            'processed_rows' => 0,
            'meta' => null,
            'failure_message' => null,
            'created_by_id' => User::factory(),
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function queued(): self
    {
        return $this->state(fn (): array => [
            'status' => ImportRun::StatusQueued,
        ]);
    }

    public function processing(): self
    {
        return $this->state(fn (): array => [
            'status' => ImportRun::StatusProcessing,
            'started_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => ImportRun::StatusCompleted,
            'processed_rows' => fake()->numberBetween(1, 20),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => ImportRun::StatusFailed,
            'failure_message' => 'Import failed.',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
