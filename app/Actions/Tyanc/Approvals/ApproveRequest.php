<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Notifications\ApprovalApprovedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ApproveRequest
{
    public function __construct(private AdvanceWorkflowStep $advanceWorkflowStep) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, ApprovalRequest $approvalRequest, array $attributes = []): ApprovalRequest
    {
        $access = resolve(PermissionResourceAccess::class);

        throw_if(
            ! $access->handle($actor, PermissionKey::cumpu('approvals', 'approve')),
            AuthorizationException::class,
        );

        throw_if(
            ! $access->handle($actor, (string) $approvalRequest->action),
            AuthorizationException::class,
        );

        $isSuperAdmin = $actor->hasRole((string) config('tyanc.reserved_roles.super_admin'));
        $reviewNote = $this->nullableString($attributes['review_note'] ?? null);

        return DB::transaction(function () use ($actor, $approvalRequest, $isSuperAdmin, $reviewNote): ApprovalRequest {
            /** @var ApprovalRequest $lockedRequest */
            $lockedRequest = ApprovalRequest::query()
                ->with([
                    'subject',
                    'requester',
                    'rule.steps.role',
                    'assignments.assignee',
                    'assignments.step.role',
                ])
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            if (! in_array($lockedRequest->status, ApprovalRequest::reviewableStatuses(), true)) {
                throw new RuntimeException(__('This approval request has already been reviewed.'));
            }

            $pendingAssignments = $lockedRequest->assignments()
                ->with('step.role')
                ->lockForUpdate()
                ->where('status', ApprovalAssignment::StatusPending)
                ->get();

            $currentStepOrder = $this->currentStepOrder($pendingAssignments);
            $pendingAssignment = $pendingAssignments
                ->filter(fn (ApprovalAssignment $assignment): bool => $this->stepOrder($assignment) === $currentStepOrder)
                ->first(fn (ApprovalAssignment $assignment): bool => $assignment->assigned_to_id === $actor->id);

            $hasAssignments = $pendingAssignments->isNotEmpty();

            throw_if(
                ! $isSuperAdmin
                && $hasAssignments
                && ! $pendingAssignment instanceof ApprovalAssignment,
                AuthorizationException::class,
            );

            throw_if(
                ! $isSuperAdmin
                && ! $hasAssignments,
                AuthorizationException::class,
            );

            if ($hasAssignments && $currentStepOrder !== null && $this->hasNextStep($lockedRequest, $currentStepOrder)) {
                return $this->advanceWorkflowStep->handle($lockedRequest, $actor, $currentStepOrder, $reviewNote);
            }

            $lockedRequest->forceFill([
                'status' => ApprovalRequest::StatusApproved,
                'review_note' => $reviewNote,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'expires_at' => now()->addMinutes($this->grantValidityMinutes($lockedRequest)),
            ])->save();

            if ($pendingAssignment instanceof ApprovalAssignment) {
                $pendingAssignment->forceFill([
                    'status' => ApprovalAssignment::StatusCompleted,
                    'completed_by_id' => $actor->id,
                    'completed_at' => now(),
                ])->save();
            }

            $lockedRequest->assignments()
                ->where('status', ApprovalAssignment::StatusPending)
                ->update([
                    'status' => ApprovalAssignment::StatusCancelled,
                    'updated_at' => now(),
                ]);

            activity('approvals')
                ->performedOn($lockedRequest->subject ?? $lockedRequest)
                ->causedBy($actor)
                ->event('approved')
                ->withProperties([
                    'approval_request_id' => (string) $lockedRequest->id,
                    'review_note' => $reviewNote,
                    'completed_step_order' => $currentStepOrder,
                    'expires_at' => $lockedRequest->expires_at?->toIso8601String(),
                ])
                ->log('Approval approved');

            if ($lockedRequest->requester instanceof User) {
                $lockedRequest->requester->notify(new ApprovalApprovedNotification($lockedRequest)->afterCommit());
            }

            return $lockedRequest->fresh([
                'requester',
                'reviewer',
                'cancelledBy',
                'consumedBy',
                'subject',
                'rule.steps.role',
                'assignments.assignee',
                'assignments.completedBy',
                'assignments.step.role',
            ]);
        });
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     */
    private function currentStepOrder(Collection $assignments): ?int
    {
        return $assignments
            ->map(fn (ApprovalAssignment $assignment): ?int => $this->stepOrder($assignment))
            ->filter(fn (mixed $stepOrder): bool => is_numeric($stepOrder))
            ->map(fn (mixed $stepOrder): int => (int) $stepOrder)
            ->sort()
            ->first();
    }

    private function hasNextStep(ApprovalRequest $approvalRequest, int $currentStepOrder): bool
    {
        $rule = $approvalRequest->rule;

        return $rule instanceof ApprovalRule && $rule->steps->contains(fn (ApprovalRuleStep $step): bool => $step->step_order > $currentStepOrder);
    }

    private function grantValidityMinutes(ApprovalRequest $approvalRequest): int
    {
        if ($approvalRequest->rule instanceof ApprovalRule && is_numeric($approvalRequest->rule->grant_validity_minutes)) {
            return max((int) $approvalRequest->rule->grant_validity_minutes, 1);
        }

        return 1440;
    }

    private function stepOrder(ApprovalAssignment $assignment): ?int
    {
        if ($assignment->step_order_snapshot !== null) {
            return (int) $assignment->step_order_snapshot;
        }

        $step = $assignment->step;

        if ($step instanceof ApprovalRuleStep) {
            return $step->step_order;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
