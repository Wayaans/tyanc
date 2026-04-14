<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Contracts\Approvals\DraftApprovalSubject;
use App\Enums\ApprovalMode;
use App\Models\ApprovalRequest;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CommitApprovedDraft
{
    /**
     * @return array{consumed: bool, stale: bool, approval: ApprovalRequest|null, result: mixed}
     */
    public function handle(User $actor, string $permissionName, Model $subject, Closure $execute): array
    {
        if (! $subject instanceof DraftApprovalSubject) {
            throw ValidationException::withMessages([
                'approval' => __('Draft approval requires a revision-aware draft subject.'),
            ]);
        }

        ApprovalRequest::expirePastDueGrants();

        return DB::transaction(function () use ($actor, $permissionName, $subject, $execute): array {
            /** @var ApprovalRequest|null $approvalRequest */
            $approvalRequest = ApprovalRequest::query()
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
                ->where('mode', ApprovalMode::Draft->value)
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', (string) $subject->getKey())
                ->whereIn('status', ApprovalRequest::consumableStatuses())
                ->latest('reviewed_at')
                ->latest('requested_at')
                ->lockForUpdate()
                ->first();

            if (! $approvalRequest instanceof ApprovalRequest) {
                return [
                    'consumed' => false,
                    'stale' => false,
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
                    'stale' => false,
                    'approval' => null,
                    'result' => null,
                ];
            }

            if (! $approvalRequest->subjectRevisionMatchesSubject()) {
                $approvalRequest->forceFill([
                    'status' => ApprovalRequest::StatusExpired,
                    'expires_at' => now(),
                ])->save();

                activity('approvals')
                    ->performedOn($subject)
                    ->causedBy($actor)
                    ->event('draft-stale')
                    ->withProperties([
                        'approval_request_id' => (string) $approvalRequest->id,
                        'subject_revision' => $approvalRequest->subject_revision,
                        'current_revision' => $subject->approvalSubjectRevision(),
                    ])
                    ->log('Approved draft expired because the draft changed after review');

                return [
                    'consumed' => false,
                    'stale' => true,
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
                ->performedOn($subject)
                ->causedBy($actor)
                ->event('consumed')
                ->withProperties([
                    'approval_request_id' => (string) $approvalRequest->id,
                    'mode' => ApprovalMode::Draft->value,
                    'subject_revision' => $approvalRequest->subject_revision,
                ])
                ->log('Approved draft committed');

            return [
                'consumed' => true,
                'stale' => false,
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
