<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use App\Notifications\NewApprovalRequestedNotification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class AdvanceWorkflowStep
{
    public function __construct(private ResolveApprovers $approvers) {}

    public function handle(ApprovalRequest $approvalRequest, User $actor, int $currentStepOrder, ?string $reviewNote = null): ApprovalRequest
    {
        $approvalRequest->loadMissing([
            'requester',
            'rule.steps.role',
            'assignments.assignee',
            'assignments.step.role',
        ]);

        if (! $approvalRequest->rule instanceof ApprovalRule) {
            throw ValidationException::withMessages([
                'approval' => __('The approval request is missing its workflow rule.'),
            ]);
        }

        $rule = $approvalRequest->rule;
        $nextStep = $rule->steps
            ->sortBy('step_order')
            ->first(fn (ApprovalRuleStep $step): bool => $step->step_order > $currentStepOrder);

        if (! $nextStep instanceof ApprovalRuleStep) {
            throw ValidationException::withMessages([
                'approval' => __('The approval workflow has no next step to advance to.'),
            ]);
        }

        $requester = $approvalRequest->requester instanceof User
            ? $approvalRequest->requester
            : $actor;

        $nextApprovers = $this->approvers->handle($requester, $rule, $nextStep);

        if ($nextApprovers->isEmpty()) {
            throw ValidationException::withMessages([
                'approval' => __('No eligible approvers are configured for the next approval step.'),
            ]);
        }

        $currentStepAssignments = $approvalRequest->assignments()
            ->lockForUpdate()
            ->where('status', ApprovalAssignment::StatusPending)
            ->get()
            ->filter(fn (ApprovalAssignment $assignment): bool => $this->stepOrder($assignment) === $currentStepOrder)
            ->values();

        $this->completeCurrentStep($currentStepAssignments, $actor);
        $this->createNextAssignments($approvalRequest, $nextStep, $nextApprovers);

        $approvalRequest->forceFill([
            'status' => ApprovalRequest::StatusInReview,
        ])->save();

        $completedStep = $currentStepAssignments->first();

        activity('approvals')
            ->performedOn($approvalRequest->subject ?? $approvalRequest)
            ->causedBy($actor)
            ->event('advanced')
            ->withProperties([
                'approval_request_id' => (string) $approvalRequest->id,
                'completed_step_order' => $currentStepOrder,
                'completed_step_label' => $completedStep?->step_label_snapshot,
                'next_step_order' => $nextStep->step_order,
                'next_step_label' => $nextStep->label,
                'review_note' => $reviewNote,
            ])
            ->log('Approval step advanced');

        $nextApprovers->each(function (User $approver) use ($approvalRequest): void {
            $approver->notify(new NewApprovalRequestedNotification($approvalRequest)->afterCommit());
        });

        return $approvalRequest->fresh([
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
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     */
    private function completeCurrentStep(Collection $assignments, User $actor): void
    {
        $actorAssignment = $assignments->first(fn (ApprovalAssignment $assignment): bool => $assignment->assigned_to_id === $actor->id);

        if ($actorAssignment instanceof ApprovalAssignment) {
            $actorAssignment->forceFill([
                'status' => ApprovalAssignment::StatusCompleted,
                'completed_by_id' => $actor->id,
                'completed_at' => now(),
            ])->save();
        }

        $assignments
            ->reject(fn (ApprovalAssignment $assignment): bool => $actorAssignment instanceof ApprovalAssignment
                && $assignment->is($actorAssignment))
            ->each(function (ApprovalAssignment $assignment): void {
                $assignment->forceFill([
                    'status' => ApprovalAssignment::StatusCancelled,
                ])->save();
            });
    }

    /**
     * @param  Collection<int, User>  $approvers
     */
    private function createNextAssignments(ApprovalRequest $approvalRequest, ApprovalRuleStep $step, Collection $approvers): void
    {
        $approvers->each(function (User $approver) use ($approvalRequest, $step): void {
            $approvalRequest->assignments()->create([
                'approval_rule_step_id' => $step->id,
                'step_order_snapshot' => $step->step_order,
                'step_label_snapshot' => $step->label,
                'role_name_snapshot' => $step->role->name,
                'assigned_to_id' => $approver->id,
                'status' => ApprovalAssignment::StatusPending,
            ]);
        });
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
}
