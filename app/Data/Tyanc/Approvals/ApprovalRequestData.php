<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRuleStep;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

final class ApprovalRequestData extends Data
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $subject_snapshot
     * @param  array<string, mixed>|null  $before_payload
     * @param  array<string, mixed>|null  $after_payload
     * @param  list<string>  $pending_assignee_names
     */
    public function __construct(
        public string $id,
        public string $action,
        public string $action_label,
        public string $status,
        public ?string $app_key,
        public ?string $resource_key,
        public ?string $action_key,
        public ?string $rule_id,
        public string $subject_name,
        public ?string $subject_type,
        public ?string $subject_id,
        public ?string $request_note,
        public ?string $review_note,
        public ?string $requested_by_id,
        public ?string $requested_by_name,
        public ?string $reviewed_by_id,
        public ?string $reviewed_by_name,
        public ?array $payload,
        public ?array $subject_snapshot,
        public ?array $before_payload,
        public ?array $after_payload,
        public ?string $impact_summary,
        public bool $is_assigned_to_actor,
        public array $pending_assignee_names,
        public ?string $current_step_label,
        public ?int $current_step_order,
        public bool $can_approve,
        public bool $can_reject,
        public bool $can_cancel,
        public bool $can_reassign,
        public bool $is_reassigned,
        public bool $is_escalated,
        public string $requested_at,
        public ?string $reviewed_at,
        public ?string $cancelled_at,
        public ?string $last_reassigned_at,
        public ?string $last_reminded_at,
        public ?string $escalated_at,
    ) {}

    public static function fromModel(ApprovalRequest $approvalRequest, ?User $actor = null): self
    {
        $approvalRequest->loadMissing('requester', 'reviewer', 'subject', 'rule', 'assignments.assignee', 'assignments.step.role');

        $access = resolve(PermissionResourceAccess::class);
        $currentAssignments = self::currentStepAssignments($approvalRequest->assignments);
        $isAssignedToActor = $actor instanceof User
            && $currentAssignments->contains(fn (ApprovalAssignment $assignment): bool => $assignment->assigned_to_id === $actor->id
                && $assignment->status === ApprovalAssignment::StatusPending);
        $isSuperAdmin = $actor instanceof User
            && $actor->hasRole((string) config('tyanc.reserved_roles.super_admin'));
        $canReviewRequest = $actor instanceof User
            && in_array($approvalRequest->status, ApprovalRequest::activeStatuses(), true)
            && ($isAssignedToActor || $isSuperAdmin)
            && $access->handle($actor, (string) $approvalRequest->action);
        $canManageApprovals = $actor instanceof User
            && $access->handle($actor, PermissionKey::cumpu('approvals', 'manage'));

        return new self(
            id: (string) $approvalRequest->id,
            action: (string) $approvalRequest->action,
            action_label: self::actionLabel($approvalRequest),
            status: (string) $approvalRequest->status,
            app_key: $approvalRequest->app_key,
            resource_key: $approvalRequest->resource_key,
            action_key: $approvalRequest->action_key,
            rule_id: $approvalRequest->rule_id,
            subject_name: self::subjectName($approvalRequest),
            subject_type: $approvalRequest->subject_type,
            subject_id: is_scalar($approvalRequest->subject_id) ? (string) $approvalRequest->subject_id : null,
            request_note: $approvalRequest->request_note,
            review_note: $approvalRequest->review_note,
            requested_by_id: $approvalRequest->requested_by_id,
            requested_by_name: $approvalRequest->requester instanceof User ? $approvalRequest->requester->name : null,
            reviewed_by_id: $approvalRequest->reviewed_by_id,
            reviewed_by_name: $approvalRequest->reviewer instanceof User ? $approvalRequest->reviewer->name : null,
            payload: is_array($approvalRequest->payload) ? $approvalRequest->payload : null,
            subject_snapshot: is_array($approvalRequest->subject_snapshot) ? $approvalRequest->subject_snapshot : null,
            before_payload: is_array($approvalRequest->before_payload) ? $approvalRequest->before_payload : null,
            after_payload: is_array($approvalRequest->after_payload) ? $approvalRequest->after_payload : null,
            impact_summary: $approvalRequest->impact_summary,
            is_assigned_to_actor: $isAssignedToActor,
            pending_assignee_names: $currentAssignments
                ->map(fn (ApprovalAssignment $assignment): string => (string) ($assignment->assignee?->name ?? __('Unknown')))
                ->unique()
                ->values()
                ->all(),
            current_step_label: self::currentStepLabel($currentAssignments),
            current_step_order: self::currentStepOrder($currentAssignments),
            can_approve: $canReviewRequest
                && $actor instanceof User
                && $access->handle($actor, PermissionKey::cumpu('approvals', 'approve')),
            can_reject: $canReviewRequest
                && $actor instanceof User
                && $access->handle($actor, PermissionKey::cumpu('approvals', 'reject')),
            can_cancel: $actor instanceof User
                && $approvalRequest->requested_by_id === $actor->id
                && in_array($approvalRequest->status, ApprovalRequest::activeStatuses(), true),
            can_reassign: $actor instanceof User
                && in_array($approvalRequest->status, ApprovalRequest::activeStatuses(), true)
                && ($isAssignedToActor || $canManageApprovals || $isSuperAdmin)
                && $approvalRequest->rule_id !== null,
            is_reassigned: $approvalRequest->last_reassigned_at !== null,
            is_escalated: $approvalRequest->escalated_at !== null,
            requested_at: $approvalRequest->requested_at?->toIso8601String() ?? now()->toIso8601String(),
            reviewed_at: $approvalRequest->reviewed_at?->toIso8601String(),
            cancelled_at: $approvalRequest->cancelled_at?->toIso8601String(),
            last_reassigned_at: $approvalRequest->last_reassigned_at?->toIso8601String(),
            last_reminded_at: $approvalRequest->last_reminded_at?->toIso8601String(),
            escalated_at: $approvalRequest->escalated_at?->toIso8601String(),
        );
    }

    private static function actionLabel(ApprovalRequest $approvalRequest): string
    {
        $label = data_get($approvalRequest->payload, 'action_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of($approvalRequest->action)
            ->replace(['.', '_'], ' ')
            ->title()
            ->value();
    }

    private static function subjectName(ApprovalRequest $approvalRequest): string
    {
        $subjectLabel = data_get($approvalRequest->payload, 'subject_label');

        if (is_string($subjectLabel) && $subjectLabel !== '') {
            return $subjectLabel;
        }

        if ($approvalRequest->subject instanceof ImportRun) {
            return (string) ($approvalRequest->subject->file_name ?? __('Users import'));
        }

        return __('Approval request');
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     * @return Collection<int, ApprovalAssignment>
     */
    private static function currentStepAssignments(Collection $assignments): Collection
    {
        $pendingAssignments = $assignments
            ->filter(fn (ApprovalAssignment $assignment): bool => $assignment->status === ApprovalAssignment::StatusPending)
            ->values();

        $currentStepOrder = self::currentStepOrder($pendingAssignments);

        if ($currentStepOrder === null) {
            return collect();
        }

        return $pendingAssignments
            ->filter(fn (ApprovalAssignment $assignment): bool => self::stepOrder($assignment) === $currentStepOrder)
            ->values();
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

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     */
    private static function currentStepLabel(Collection $assignments): ?string
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
