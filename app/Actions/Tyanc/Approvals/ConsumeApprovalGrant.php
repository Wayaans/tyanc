<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalRequest;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class ConsumeApprovalGrant
{
    /**
     * @return array{consumed: bool, approval: ApprovalRequest|null, result: mixed}
     */
    public function handle(
        User $actor,
        string $permissionName,
        ?Model $subject,
        Closure $execute,
    ): array {
        ApprovalRequest::expirePastDueGrants();

        return DB::transaction(function () use ($actor, $permissionName, $subject, $execute): array {
            $query = ApprovalRequest::query()
                ->with([
                    'requester',
                    'reviewer',
                    'cancelledBy',
                    'consumedBy',
                    'subject',
                    'rule.steps.role',
                    'assignments.assignee',
                    'assignments.completedBy',
                    'assignments.step.role',
                ])
                ->where('requested_by_id', $actor->id)
                ->where('action', $permissionName)
                ->whereIn('status', ApprovalRequest::consumableStatuses())
                ->latest('reviewed_at')
                ->latest('requested_at')
                ->lockForUpdate();

            if ($subject instanceof Model && $subject->getKey() !== null) {
                $query
                    ->where('subject_type', $subject->getMorphClass())
                    ->where('subject_id', (string) $subject->getKey());
            } else {
                $query
                    ->whereNull('subject_type')
                    ->whereNull('subject_id');
            }

            /** @var ApprovalRequest|null $approvalRequest */
            $approvalRequest = $query->first();

            if (! $approvalRequest instanceof ApprovalRequest) {
                return [
                    'consumed' => false,
                    'approval' => null,
                    'result' => null,
                ];
            }

            if ($approvalRequest->grantHasExpired()) {
                $approvalRequest->forceFill([
                    'status' => ApprovalRequest::StatusExpired,
                ])->save();

                return [
                    'consumed' => false,
                    'approval' => null,
                    'result' => null,
                ];
            }

            $result = $execute();

            $approvalRequest->forceFill([
                'status' => ApprovalRequest::StatusConsumed,
                'consumed_by_id' => $actor->id,
                'consumed_at' => now(),
            ])->save();

            activity('approvals')
                ->performedOn($subject ?? $approvalRequest->subject ?? $approvalRequest)
                ->causedBy($actor)
                ->event('consumed')
                ->withProperties([
                    'approval_request_id' => (string) $approvalRequest->id,
                    'permission_name' => $permissionName,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => is_scalar($subject?->getKey()) ? (string) $subject?->getKey() : null,
                ])
                ->log('Approval grant consumed');

            return [
                'consumed' => true,
                'approval' => $approvalRequest->fresh([
                    'requester',
                    'reviewer',
                    'cancelledBy',
                    'consumedBy',
                    'subject',
                    'rule.steps.role',
                    'assignments.assignee',
                    'assignments.completedBy',
                    'assignments.step.role',
                ]),
                'result' => $result,
            ];
        });
    }
}
