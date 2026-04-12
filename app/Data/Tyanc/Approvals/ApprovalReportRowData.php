<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Approvals;

use App\Contracts\Approvals\ApprovalSubject;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

final class ApprovalReportRowData extends Data
{
    /**
     * @param  list<string>  $current_assignee_names
     */
    public function __construct(
        public string $id,
        public ?string $rule_id,
        public ?string $app_key,
        public ?string $app_label,
        public ?string $resource_key,
        public ?string $action_key,
        public string $action_label,
        public string $status,
        public string $subject_name,
        public ?string $requested_by_name,
        public ?string $reviewed_by_name,
        public ?string $consumed_by_name,
        public array $current_assignee_names,
        public ?string $current_step_label,
        public ?int $current_step_order,
        public bool $is_overdue,
        public bool $is_reassigned,
        public bool $is_escalated,
        public bool $is_grant_usable,
        public string $requested_at,
        public ?string $reviewed_at,
        public ?string $expires_at,
        public ?string $consumed_at,
        public ?string $escalated_at,
        public ?string $last_reassigned_at,
        public ?float $turnaround_hours,
    ) {}

    public static function fromModel(ApprovalRequest $approvalRequest, bool $isOverdue = false): self
    {
        $approvalRequest->loadMissing([
            'requester',
            'reviewer',
            'consumedBy',
            'subject',
            'assignments.assignee',
            'assignments.step.role',
        ]);

        $currentAssignments = $approvalRequest->assignments
            ->filter(fn (ApprovalAssignment $assignment): bool => $assignment->status === ApprovalAssignment::StatusPending);

        $currentStepOrder = self::currentStepOrder($currentAssignments);
        $currentStepAssignments = $currentAssignments
            ->filter(fn (ApprovalAssignment $assignment): bool => self::stepOrder($assignment) === $currentStepOrder)
            ->values();

        return new self(
            id: (string) $approvalRequest->id,
            rule_id: $approvalRequest->rule_id,
            app_key: $approvalRequest->app_key,
            app_label: is_string($approvalRequest->app_key) ? PermissionKey::appLabel($approvalRequest->app_key) : null,
            resource_key: $approvalRequest->resource_key,
            action_key: $approvalRequest->action_key,
            action_label: self::actionLabel($approvalRequest),
            status: $approvalRequest->effectiveStatus(),
            subject_name: self::subjectName($approvalRequest),
            requested_by_name: $approvalRequest->requester instanceof User ? $approvalRequest->requester->name : null,
            reviewed_by_name: $approvalRequest->reviewer instanceof User ? $approvalRequest->reviewer->name : null,
            consumed_by_name: $approvalRequest->consumedBy instanceof User ? $approvalRequest->consumedBy->name : null,
            current_assignee_names: $currentStepAssignments
                ->map(fn (ApprovalAssignment $assignment): string => (string) ($assignment->assignee?->name ?? __('Unknown')))
                ->unique()
                ->values()
                ->all(),
            current_step_label: self::stepLabel($currentStepAssignments),
            current_step_order: $currentStepOrder,
            is_overdue: $isOverdue,
            is_reassigned: $approvalRequest->last_reassigned_at !== null,
            is_escalated: $approvalRequest->escalated_at !== null,
            is_grant_usable: $approvalRequest->isGrantConsumable(),
            requested_at: $approvalRequest->requested_at?->toIso8601String() ?? now()->toIso8601String(),
            reviewed_at: $approvalRequest->reviewed_at?->toIso8601String(),
            expires_at: $approvalRequest->expires_at?->toIso8601String(),
            consumed_at: $approvalRequest->consumed_at?->toIso8601String(),
            escalated_at: $approvalRequest->escalated_at?->toIso8601String(),
            last_reassigned_at: $approvalRequest->last_reassigned_at?->toIso8601String(),
            turnaround_hours: self::turnaroundHours($approvalRequest),
        );
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     */
    private static function currentStepOrder(Collection $assignments): ?int
    {
        return $assignments
            ->map(fn (ApprovalAssignment $assignment): ?int => self::stepOrder($assignment))
            ->filter(fn (mixed $stepOrder): bool => is_numeric($stepOrder))
            ->map(fn (mixed $stepOrder): int => (int) $stepOrder)
            ->sort()
            ->first();
    }

    private static function actionLabel(ApprovalRequest $approvalRequest): string
    {
        $label = data_get($approvalRequest->payload, 'action_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of((string) $approvalRequest->action)
            ->replace(['.', '_'], ' ')
            ->title()
            ->value();
    }

    private static function subjectName(ApprovalRequest $approvalRequest): string
    {
        $label = data_get($approvalRequest->payload, 'subject_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        if ($approvalRequest->subject instanceof ApprovalSubject) {
            return $approvalRequest->subject->approvalSubjectLabel();
        }

        return __('Approval request');
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     */
    private static function stepLabel(Collection $assignments): ?string
    {
        /** @var ApprovalAssignment|null $assignment */
        $assignment = $assignments->first();

        if (! $assignment instanceof ApprovalAssignment) {
            return null;
        }

        if (is_string($assignment->step_label_snapshot) && $assignment->step_label_snapshot !== '') {
            return $assignment->step_label_snapshot;
        }

        return $assignment->step instanceof ApprovalRuleStep
            ? $assignment->step->label
            : null;
    }

    private static function turnaroundHours(ApprovalRequest $approvalRequest): ?float
    {
        if (! $approvalRequest->requested_at instanceof CarbonInterface || ! $approvalRequest->reviewed_at instanceof CarbonInterface) {
            return null;
        }

        return round($approvalRequest->requested_at->diffInMinutes($approvalRequest->reviewed_at) / 60, 2);
    }

    private static function stepOrder(ApprovalAssignment $assignment): ?int
    {
        if (is_numeric($assignment->step_order_snapshot)) {
            return (int) $assignment->step_order_snapshot;
        }

        if (is_numeric($assignment->step?->step_order)) {
            return (int) $assignment->step?->step_order;
        }

        return null;
    }
}
