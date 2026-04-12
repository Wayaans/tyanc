<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\ApprovalCancelledNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CancelRequest
{
    public function handle(User $actor, ApprovalRequest $approvalRequest): ApprovalRequest
    {
        $canManageApprovals = resolve(PermissionResourceAccess::class)->handle(
            $actor,
            PermissionKey::cumpu('approvals', 'manage'),
        );

        throw_if(
            $approvalRequest->requested_by_id !== $actor->id && ! $canManageApprovals,
            AuthorizationException::class,
        );

        return DB::transaction(function () use ($actor, $approvalRequest): ApprovalRequest {
            /** @var ApprovalRequest $lockedRequest */
            $lockedRequest = ApprovalRequest::query()
                ->with(['assignments.assignee', 'subject'])
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            if (! in_array($lockedRequest->status, ApprovalRequest::reviewableStatuses(), true)) {
                throw new RuntimeException(__('Only pending approval requests can be cancelled.'));
            }

            $lockedRequest->forceFill([
                'status' => ApprovalRequest::StatusCancelled,
                'cancelled_by_id' => $actor->id,
                'cancelled_at' => now(),
            ])->save();

            $lockedRequest->assignments()
                ->where('status', ApprovalAssignment::StatusPending)
                ->update([
                    'status' => ApprovalAssignment::StatusCancelled,
                    'updated_at' => now(),
                ]);

            activity('approvals')
                ->performedOn($lockedRequest->subject ?? $lockedRequest)
                ->causedBy($actor)
                ->event('cancelled')
                ->withProperties([
                    'approval_request_id' => (string) $lockedRequest->id,
                ])
                ->log('Approval cancelled');

            $lockedRequest->assignments
                ->pluck('assignee')
                ->filter(fn (mixed $user): bool => $user instanceof User)
                ->unique('id')
                ->each(function (User $assignee) use ($lockedRequest): void {
                    $assignee->notify(new ApprovalCancelledNotification($lockedRequest)->afterCommit());
                });

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
}
