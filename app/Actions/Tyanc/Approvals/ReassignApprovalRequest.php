<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Notifications\ApprovalReassignedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReassignApprovalRequest
{
    public function __construct(private ResolveApprovers $approvers) {}

    public function handle(
        User $actor,
        ApprovalRequest $approvalRequest,
        ApprovalAssignment $approvalAssignment,
        string $assignedToId,
        ?string $note = null,
    ): ApprovalRequest {
        $approvalRequest->loadMissing([
            'requester',
            'rule.steps.role',
            'assignments.assignee',
            'assignments.step.role',
        ]);

        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approvals', 'viewany')),
            AuthorizationException::class,
        );

        throw_if(
            ! in_array($approvalRequest->status, ApprovalRequest::activeStatuses(), true),
            ValidationException::withMessages([
                'approval' => __('Only active approval requests can be reassigned.'),
            ]),
        );

        throw_if(
            ! $approvalRequest->rule instanceof ApprovalRule,
            ValidationException::withMessages([
                'approval' => __('The approval request is missing its workflow rule.'),
            ]),
        );

        throw_if(
            $approvalAssignment->approval_request_id !== $approvalRequest->id,
            ValidationException::withMessages([
                'assignment_id' => __('The selected assignment does not belong to this approval request.'),
            ]),
        );

        $isSuperAdmin = $actor->hasRole((string) config('tyanc.reserved_roles.super_admin'));
        $canManage = resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::cumpu('approvals', 'manage'));

        return DB::transaction(function () use ($actor, $approvalRequest, $approvalAssignment, $assignedToId, $canManage, $isSuperAdmin, $note): ApprovalRequest {
            /** @var ApprovalRequest $lockedRequest */
            $lockedRequest = ApprovalRequest::query()
                ->with([
                    'requester',
                    'rule.steps.role',
                    'assignments.assignee',
                    'assignments.step.role',
                ])
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            /** @var ApprovalAssignment $lockedAssignment */
            $lockedAssignment = $lockedRequest->assignments()
                ->with('step.role')
                ->lockForUpdate()
                ->findOrFail($approvalAssignment->id);

            if ($lockedAssignment->status !== ApprovalAssignment::StatusPending) {
                throw ValidationException::withMessages([
                    'assignment_id' => __('Only pending assignments can be reassigned.'),
                ]);
            }

            $stepOrder = $this->stepOrder($lockedAssignment);

            if ($stepOrder === null) {
                throw ValidationException::withMessages([
                    'assignment_id' => __('The selected assignment does not have a valid workflow step.'),
                ]);
            }

            $currentStepAssignments = $lockedRequest->assignments()
                ->with('step.role')
                ->lockForUpdate()
                ->where('status', ApprovalAssignment::StatusPending)
                ->get()
                ->filter(fn (ApprovalAssignment $assignment): bool => $this->stepOrder($assignment) === $stepOrder)
                ->values();

            $actorOwnsCurrentStep = $currentStepAssignments
                ->contains(fn (ApprovalAssignment $assignment): bool => $assignment->assigned_to_id === $actor->id);

            throw_if(
                ! $isSuperAdmin && ! $canManage && ! $actorOwnsCurrentStep,
                AuthorizationException::class,
            );

            $step = $lockedAssignment->step;

            if (! $step instanceof ApprovalRuleStep) {
                throw ValidationException::withMessages([
                    'assignment_id' => __('The selected assignment is missing its workflow step.'),
                ]);
            }

            $requester = $lockedRequest->requester instanceof User
                ? $lockedRequest->requester
                : $actor;

            $eligibleApprovers = $this->approvers->handle($requester, $lockedRequest->rule, $step);
            $targetAssignee = $eligibleApprovers->first(fn (User $user): bool => $user->id === $assignedToId);

            if (! $targetAssignee instanceof User) {
                throw ValidationException::withMessages([
                    'assigned_to_id' => __('Choose an eligible approver for this workflow step.'),
                ]);
            }

            $targetAssignment = $lockedRequest->assignments()
                ->lockForUpdate()
                ->where('approval_rule_step_id', $step->id)
                ->where('assigned_to_id', $targetAssignee->id)->latest()
                ->first();

            if ($targetAssignment instanceof ApprovalAssignment) {
                if ($targetAssignment->status === ApprovalAssignment::StatusCompleted) {
                    throw ValidationException::withMessages([
                        'assigned_to_id' => __('The selected approver has already completed this workflow step.'),
                    ]);
                }

                $targetAssignment->forceFill([
                    'status' => ApprovalAssignment::StatusPending,
                    'completed_by_id' => null,
                    'completed_at' => null,
                ])->save();
            } else {
                $targetAssignment = $lockedRequest->assignments()->create([
                    'approval_rule_step_id' => $step->id,
                    'step_order_snapshot' => $step->step_order,
                    'step_label_snapshot' => $step->label,
                    'role_name_snapshot' => $step->role?->name,
                    'assigned_to_id' => $targetAssignee->id,
                    'status' => ApprovalAssignment::StatusPending,
                ]);
            }

            $currentStepAssignments
                ->reject(fn (ApprovalAssignment $assignment): bool => $assignment->id === $targetAssignment->id)
                ->each(function (ApprovalAssignment $assignment): void {
                    if ($assignment->status !== ApprovalAssignment::StatusPending) {
                        return;
                    }

                    $assignment->forceFill([
                        'status' => ApprovalAssignment::StatusCancelled,
                    ])->save();
                });

            $lockedRequest->forceFill([
                'last_reassigned_at' => now(),
            ])->save();

            activity('approvals')
                ->performedOn($lockedRequest->subject ?? $lockedRequest)
                ->causedBy($actor)
                ->event('reassigned')
                ->withProperties([
                    'approval_request_id' => (string) $lockedRequest->id,
                    'assignment_id' => (string) $lockedAssignment->id,
                    'step_order' => $stepOrder,
                    'reassigned_to_id' => $targetAssignee->id,
                    'note' => $this->nullableString($note),
                ])
                ->log('Approval reassigned');

            $targetAssignee->notify(new ApprovalReassignedNotification($lockedRequest)->afterCommit());

            return $lockedRequest->fresh([
                'requester',
                'reviewer',
                'cancelledBy',
                'subject',
                'rule.steps.role',
                'assignments.assignee',
                'assignments.completedBy',
                'assignments.step.role',
            ]);
        });
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private function stepOrder(ApprovalAssignment $assignment): ?int
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
