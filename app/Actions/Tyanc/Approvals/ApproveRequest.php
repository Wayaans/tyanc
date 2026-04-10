<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Jobs\ProcessUsersImport;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ApproveRequest
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, ApprovalRequest $approvalRequest, array $attributes = []): ApprovalRequest
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($actor, PermissionKey::tyanc('approvals', 'approve')),
            AuthorizationException::class,
        );

        if ($approvalRequest->status !== ApprovalRequest::StatusPending) {
            throw new RuntimeException(__('This approval request has already been reviewed.'));
        }

        return DB::transaction(function () use ($actor, $approvalRequest, $attributes): ApprovalRequest {
            $approvalRequest->forceFill([
                'status' => ApprovalRequest::StatusApproved,
                'review_note' => $this->nullableString($attributes['review_note'] ?? null),
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            if ($approvalRequest->subject instanceof ImportRun) {
                $approvalRequest->subject->forceFill([
                    'status' => ImportRun::StatusQueued,
                    'failure_message' => null,
                ])->save();

                dispatch(new ProcessUsersImport((string) $approvalRequest->subject->id))->afterCommit();

                activity('imports')
                    ->performedOn($approvalRequest->subject)
                    ->causedBy($actor)
                    ->event('queued')
                    ->withProperties([
                        'approval_request_id' => (string) $approvalRequest->id,
                    ])
                    ->log('Users import queued');
            }

            activity('approvals')
                ->performedOn($approvalRequest->subject ?? $approvalRequest)
                ->causedBy($actor)
                ->event('approved')
                ->withProperties([
                    'attributes' => $approvalRequest->fresh()->toArray(),
                ])
                ->log('Approval approved');

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
