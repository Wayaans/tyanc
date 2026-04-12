<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
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
            'rule_id' => ApprovalRule::factory()->withRoleStep(),
            'action' => PermissionKey::tyanc('users', 'import'),
            'app_key' => 'tyanc',
            'resource_key' => 'users',
            'action_key' => 'import',
            'status' => ApprovalRequest::StatusPending,
            'subject_type' => ImportRun::class,
            'subject_id' => ImportRun::factory(),
            'requested_by_id' => User::factory(),
            'reviewed_by_id' => null,
            'cancelled_by_id' => null,
            'consumed_by_id' => null,
            'request_note' => fake()->sentence(),
            'review_note' => null,
            'payload' => [
                'action_label' => 'Users import',
                'subject_label' => 'users.xlsx',
            ],
            'subject_snapshot' => null,
            'requested_at' => now(),
            'reviewed_at' => null,
            'cancelled_at' => null,
            'expires_at' => null,
            'consumed_at' => null,
            'superseded_at' => null,
            'last_reassigned_at' => null,
            'last_reminded_at' => null,
            'escalated_at' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => ApprovalRequest::StatusApproved,
            'reviewed_by_id' => User::factory(),
            'review_note' => fake()->sentence(),
            'reviewed_at' => now(),
            'expires_at' => now()->addDay(),
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

    public function cancelled(): self
    {
        return $this->state(fn (): array => [
            'status' => ApprovalRequest::StatusCancelled,
            'cancelled_by_id' => User::factory(),
            'cancelled_at' => now(),
        ]);
    }

    public function consumed(): self
    {
        return $this->approved()->state(fn (): array => [
            'status' => ApprovalRequest::StatusConsumed,
            'consumed_by_id' => User::factory(),
            'consumed_at' => now(),
        ]);
    }
}
