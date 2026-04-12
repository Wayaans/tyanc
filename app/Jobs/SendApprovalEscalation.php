<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tyanc\Approvals\FindOverdueApprovals;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\ApprovalEscalatedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Throwable;

final class SendApprovalEscalation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public string $approvalRequestId) {}

    public function handle(FindOverdueApprovals $overdueApprovals): void
    {
        $approvalRequest = ApprovalRequest::query()
            ->with(['requester', 'subject', 'assignments.assignee', 'assignments.step.role', 'rule'])
            ->find($this->approvalRequestId);

        if (
            ! $approvalRequest instanceof ApprovalRequest
            || ! in_array($approvalRequest->status, ApprovalRequest::activeStatuses(), true)
            || $approvalRequest->assignments->where('status', ApprovalAssignment::StatusPending)->isEmpty()
            || ! $overdueApprovals->isEscalationDue($approvalRequest)
        ) {
            return;
        }

        $this->notifiableUsers($approvalRequest)
            ->each(function (User $user) use ($approvalRequest): void {
                $user->notify(new ApprovalEscalatedNotification($approvalRequest));
            });

        $approvalRequest->forceFill([
            'escalated_at' => now(),
        ])->save();

        activity('approvals')
            ->performedOn($approvalRequest->subject ?? $approvalRequest)
            ->event('escalated')
            ->withProperties([
                'approval_request_id' => (string) $approvalRequest->id,
            ])
            ->log('Approval escalated');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function failed(?Throwable $exception): void
    {
        // No additional recovery action is required. The command can redispatch the escalation later.
    }

    public function uniqueId(): string
    {
        return sprintf('approval-escalation:%s', $this->approvalRequestId);
    }

    /**
     * @return Collection<int, User>
     */
    private function notifiableUsers(ApprovalRequest $approvalRequest): Collection
    {
        $pendingAssignees = $approvalRequest->assignments
            ->filter(fn (ApprovalAssignment $assignment): bool => $assignment->status === ApprovalAssignment::StatusPending)
            ->pluck('assignee')
            ->filter(fn (mixed $user): bool => $user instanceof User);

        return $pendingAssignees
            ->when(
                $approvalRequest->requester instanceof User,
                fn (Collection $collection): Collection => $collection->push($approvalRequest->requester),
            )
            ->unique('id')
            ->values();
    }
}
