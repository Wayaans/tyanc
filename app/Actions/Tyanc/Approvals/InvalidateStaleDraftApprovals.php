<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Contracts\Approvals\DraftApprovalSubject;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class InvalidateStaleDraftApprovals
{
    public function handle(string $permissionName, Model $subject, ?User $actor = null): int
    {
        if (! $subject instanceof DraftApprovalSubject) {
            return 0;
        }

        $currentRevision = $subject->approvalSubjectRevision();
        $now = now();

        return DB::transaction(fn (): int => ApprovalRequest::query()
            ->where('action', $permissionName)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->whereIn('status', ApprovalRequest::blockingStatuses())
            ->where(function ($query) use ($currentRevision): void {
                $query
                    ->whereNull('subject_revision')
                    ->orWhere('subject_revision', '!=', $currentRevision);
            })
            ->lockForUpdate()
            ->get()
            ->reduce(function (int $count, ApprovalRequest $approvalRequest) use ($actor, $now, $currentRevision, $subject): int {
                $approvalRequest->forceFill(match ($approvalRequest->status) {
                    ApprovalRequest::StatusApproved => [
                        'status' => ApprovalRequest::StatusExpired,
                        'expires_at' => $now,
                    ],
                    default => [
                        'status' => ApprovalRequest::StatusCancelled,
                        'cancelled_by_id' => $actor?->id,
                        'cancelled_at' => $now,
                    ],
                })->save();

                activity('approvals')
                    ->performedOn($subject)
                    ->causedBy($actor)
                    ->event('draft-invalidated')
                    ->withProperties([
                        'approval_request_id' => (string) $approvalRequest->id,
                        'previous_revision' => $approvalRequest->subject_revision,
                        'current_revision' => $currentRevision,
                    ])
                    ->log('Stale draft approval invalidated');

                return $count + 1;
            }, 0));
    }
}
