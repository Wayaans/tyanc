<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
final class ApprovalRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => 'tyanc.users.import',
            'status' => ApprovalRequest::StatusPending,
            'subject_type' => ImportRun::class,
            'subject_id' => ImportRun::factory(),
            'requested_by_id' => User::factory(),
            'reviewed_by_id' => null,
            'request_note' => fake()->sentence(),
            'review_note' => null,
            'payload' => null,
            'requested_at' => now(),
            'reviewed_at' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => ApprovalRequest::StatusApproved,
            'reviewed_by_id' => User::factory(),
            'review_note' => fake()->sentence(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'status' => ApprovalRequest::StatusRejected,
            'reviewed_by_id' => User::factory(),
            'review_note' => fake()->sentence(),
            'reviewed_at' => now(),
        ]);
    }
}
