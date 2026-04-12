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
use Illuminate\Support\Facades\Storage;
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
                ->with(['actionRecord', 'assignments.assignee', 'subject'])
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            if (! in_array($lockedRequest->status, ApprovalRequest::activeStatuses(), true)) {
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

            $stagedFilePath = data_get($lockedRequest->actionRecord?->payload, 'staged_file_path');
            if (is_string($stagedFilePath) && $stagedFilePath !== '') {
                Storage::disk('local')->delete($stagedFilePath);
            }

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
                ->each(fn (User $assignee): mixed => $assignee->notify(
                    new ApprovalCancelledNotification($lockedRequest)->afterCommit(),
                ));

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
}
