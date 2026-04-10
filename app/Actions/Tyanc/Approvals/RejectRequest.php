<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
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
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('approvals', 'reject')),
            AuthorizationException::class,
        );

        if ($approvalRequest->status !== ApprovalRequest::StatusPending) {
            throw new RuntimeException(__('This approval request has already been reviewed.'));
        }

        return DB::transaction(function () use ($actor, $approvalRequest, $attributes): ApprovalRequest {
            $approvalRequest->forceFill([
                'status' => ApprovalRequest::StatusRejected,
                'review_note' => $this->nullableString($attributes['review_note'] ?? null),
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            if ($approvalRequest->subject instanceof ImportRun) {
                $approvalRequest->subject->forceFill([
                    'status' => ImportRun::StatusFailed,
                    'failure_message' => __('Import request was rejected.'),
                    'finished_at' => now(),
                ])->save();
            }

            activity('approvals')
                ->performedOn($approvalRequest->subject ?? $approvalRequest)
                ->causedBy($actor)
                ->event('rejected')
                ->withProperties([
                    'attributes' => $approvalRequest->fresh()->toArray(),
                ])
                ->log('Approval rejected');

            return $approvalRequest->fresh(['requester', 'reviewer', 'subject']);
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
