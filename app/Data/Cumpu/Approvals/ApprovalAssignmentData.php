<?php

declare(strict_types=1);

namespace App\Data\Cumpu\Approvals;

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class ApprovalAssignmentData extends Data
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $assigned_to_id,
        public ?string $assigned_to_name,
        public ?string $completed_by_id,
        public ?string $completed_by_name,
        public ?string $step_label,
        public ?int $step_order,
        public ?string $role_name,
        public string $assigned_at,
        public ?string $completed_at,
    ) {}

    public static function fromModel(ApprovalAssignment $assignment): self
    {
        return new self(
            id: (string) $assignment->id,
            status: (string) $assignment->status,
            assigned_to_id: $assignment->assigned_to_id,
            assigned_to_name: $assignment->assignee instanceof User ? $assignment->assignee->name : null,
            completed_by_id: $assignment->completed_by_id,
            completed_by_name: $assignment->completedBy instanceof User ? $assignment->completedBy->name : null,
            step_label: $assignment->step instanceof ApprovalRuleStep ? $assignment->step->label : null,
            step_order: $assignment->step instanceof ApprovalRuleStep ? $assignment->step->step_order : null,
            role_name: $assignment->step?->role?->name,
            assigned_at: $assignment->created_at instanceof CarbonInterface ? $assignment->created_at->toIso8601String() : now()->toIso8601String(),
            completed_at: $assignment->completed_at instanceof CarbonInterface ? $assignment->completed_at->toIso8601String() : null,
        );
    }
}
