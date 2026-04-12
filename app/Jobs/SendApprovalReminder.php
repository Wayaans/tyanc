<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tyanc\Approvals\FindOverdueApprovals;
use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\ApprovalReminderNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Throwable;

final class SendApprovalReminder implements ShouldBeUnique, ShouldQueue
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
            || ! $overdueApprovals->isReminderDue($approvalRequest)
        ) {
            return;
        }

        $this->notifiableAssignees($approvalRequest)
            ->each(function (User $user) use ($approvalRequest): void {
                $user->notify(new ApprovalReminderNotification($approvalRequest));
            });

        $approvalRequest->forceFill([
            'last_reminded_at' => now(),
        ])->save();

        activity('approvals')
            ->performedOn($approvalRequest->subject ?? $approvalRequest)
            ->event('reminder-sent')
            ->withProperties([
                'approval_request_id' => (string) $approvalRequest->id,
            ])
            ->log('Approval reminder sent');
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
        // No additional recovery action is required. The command can redispatch the reminder later.
    }

    public function uniqueId(): string
    {
        return sprintf('approval-reminder:%s', $this->approvalRequestId);
    }

    /**
     * @return Collection<int, User>
     */
    private function notifiableAssignees(ApprovalRequest $approvalRequest): Collection
    {
        return $approvalRequest->assignments
            ->filter(fn (ApprovalAssignment $assignment): bool => $assignment->status === ApprovalAssignment::StatusPending)
            ->pluck('assignee')
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();
    }
}
