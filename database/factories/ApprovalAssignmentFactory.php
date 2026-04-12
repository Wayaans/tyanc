<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalAssignment>
 */
final class ApprovalAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'approval_rule_step_id' => ApprovalRuleStep::factory(),
            'assigned_to_id' => User::factory(),
            'status' => ApprovalAssignment::StatusPending,
            'completed_by_id' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => ApprovalAssignment::StatusCompleted,
            'completed_by_id' => User::factory(),
            'completed_at' => now(),
        ]);
    }
}
