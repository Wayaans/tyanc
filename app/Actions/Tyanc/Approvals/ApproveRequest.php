<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Jobs\ProcessUsersImport;
use App\Models\ApprovalAction;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use App\Notifications\ApprovalApprovedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ApproveRequest
{
    public function __construct(private ExecuteApprovedAction $executor) {}

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

        return DB::transaction(function () use ($actor, $approvalRequest, $attributes, $isSuperAdmin): ApprovalRequest {
            /** @var ApprovalRequest $lockedRequest */
            $lockedRequest = ApprovalRequest::query()
                ->with(['subject', 'actionRecord', 'requester'])
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            if (! in_array($lockedRequest->status, ApprovalRequest::activeStatuses(), true)) {
                throw new RuntimeException(__('This approval request has already been reviewed.'));
            }

            $pendingAssignment = $lockedRequest->assignments()
                ->lockForUpdate()
                ->where('assigned_to_id', $actor->id)
                ->where('status', ApprovalAssignment::StatusPending)
                ->first();

            $hasAssignments = $lockedRequest->assignments()->lockForUpdate()->exists();
            $isLegacyImportRequest = ! $lockedRequest->actionRecord instanceof ApprovalAction
                && $lockedRequest->subject instanceof ImportRun;

            throw_if(
                ! $isSuperAdmin
                && $hasAssignments
                && ! $pendingAssignment instanceof ApprovalAssignment,
                AuthorizationException::class,
            );

            throw_if(
                ! $isSuperAdmin
                && ! $hasAssignments
                && ! $isLegacyImportRequest,
                AuthorizationException::class,
            );

            $lockedRequest->forceFill([
                'status' => ApprovalRequest::StatusApproved,
                'review_note' => $this->nullableString($attributes['review_note'] ?? null),
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

            if ($lockedRequest->actionRecord instanceof ApprovalAction) {
                $this->executor->handle($lockedRequest);
            } elseif ($lockedRequest->subject instanceof ImportRun) {
                $lockedRequest->subject->forceFill([
                    'status' => ImportRun::StatusQueued,
                    'failure_message' => null,
                ])->save();

                dispatch(new ProcessUsersImport((string) $lockedRequest->subject->id))->afterCommit();
            }

            activity('approvals')
                ->performedOn($lockedRequest->subject ?? $lockedRequest)
                ->causedBy($actor)
                ->event('approved')
                ->withProperties([
                    'approval_request_id' => (string) $lockedRequest->id,
                ])
                ->log('Approval approved');

            if ($lockedRequest->requester instanceof User) {
                $lockedRequest->requester->notify(new ApprovalApprovedNotification($lockedRequest)->afterCommit());
            }

            return $lockedRequest->fresh([
                'requester',
                'reviewer',
                'cancelledBy',
                'subject',
                'assignments.assignee',
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
