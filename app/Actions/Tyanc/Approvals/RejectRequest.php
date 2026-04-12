<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\ApprovalRejectedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class RejectRequest
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, ApprovalRequest $approvalRequest, array $attributes = []): ApprovalRequest
    {
        $access = resolve(PermissionResourceAccess::class);

        throw_if(
            ! $access->handle($actor, PermissionKey::cumpu('approvals', 'reject')),
            AuthorizationException::class,
        );

        throw_if(
            ! $access->handle($actor, (string) $approvalRequest->action),
            AuthorizationException::class,
        );

        $isSuperAdmin = $actor->hasRole((string) config('tyanc.reserved_roles.super_admin'));

        return DB::transaction(function () use ($actor, $approvalRequest, $attributes, $isSuperAdmin): ApprovalRequest {
            /** @var ApprovalRequest $lockedRequest */
            $lockedRequest = ApprovalRequest::query()
                ->with(['subject', 'requester'])
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            if (! in_array($lockedRequest->status, ApprovalRequest::reviewableStatuses(), true)) {
                throw new RuntimeException(__('This approval request has already been reviewed.'));
            }

            $pendingAssignment = $lockedRequest->assignments()
                ->lockForUpdate()
                ->where('assigned_to_id', $actor->id)
                ->where('status', ApprovalAssignment::StatusPending)
                ->first();

            $hasAssignments = $lockedRequest->assignments()->lockForUpdate()->exists();

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

            $reviewNote = $this->nullableString($attributes['review_note'] ?? null);

            $lockedRequest->forceFill([
                'status' => ApprovalRequest::StatusRejected,
                'review_note' => $reviewNote,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
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
                ->event('rejected')
                ->withProperties([
                    'approval_request_id' => (string) $lockedRequest->id,
                    'review_note' => $reviewNote,
                ])
                ->log('Approval rejected');

            if ($lockedRequest->requester instanceof User) {
                $lockedRequest->requester->notify(new ApprovalRejectedNotification($lockedRequest)->afterCommit());
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
