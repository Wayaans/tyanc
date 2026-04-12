<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalAction;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use App\Notifications\ApprovalRejectedNotification;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'status' => ApprovalRequest::StatusRejected,
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

            $stagedFilePath = data_get($lockedRequest->actionRecord?->payload, 'staged_file_path');
            if (is_string($stagedFilePath) && $stagedFilePath !== '') {
                Storage::disk('local')->delete($stagedFilePath);
            }

            if (! $lockedRequest->actionRecord instanceof ApprovalAction && $lockedRequest->subject instanceof ImportRun) {
                $lockedRequest->subject->forceFill([
                    'status' => ImportRun::StatusFailed,
                    'failure_message' => __('Import request was rejected.'),
                    'finished_at' => now(),
                ])->save();
            }

            activity('approvals')
                ->performedOn($lockedRequest->subject ?? $lockedRequest)
                ->causedBy($actor)
                ->event('rejected')
                ->withProperties([
                    'approval_request_id' => (string) $lockedRequest->id,
                ])
                ->log('Approval rejected');

            if ($lockedRequest->requester instanceof User) {
                $lockedRequest->requester->notify(new ApprovalRejectedNotification($lockedRequest)->afterCommit());
            }

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
}
