<?php

declare(strict_types=1);

namespace App\Actions\Tyanc\Approvals;

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final readonly class FindOverdueApprovals
{
    /**
     * @return Collection<int, ApprovalRequest>
     */
    public function handle(string $scope = 'overdue', ?CarbonInterface $referenceTime = null): Collection
    {
        $resolvedReferenceTime = $referenceTime?->copy() ?? now();

        return ApprovalRequest::query()
            ->with([
                'rule',
                'assignments' => fn ($query) => $query
                    ->where('status', ApprovalAssignment::StatusPending)
                    ->orderBy('created_at'),
            ])
            ->whereIn('status', ApprovalRequest::activeStatuses())
            ->whereHas('assignments', fn ($query) => $query->where('status', ApprovalAssignment::StatusPending))
            ->get()
            ->filter(fn (ApprovalRequest $approvalRequest): bool => match ($scope) {
                'reminder' => $this->isReminderDue($approvalRequest, $resolvedReferenceTime),
                'escalation' => $this->isEscalationDue($approvalRequest, $resolvedReferenceTime),
                default => $this->isOverdue($approvalRequest, $resolvedReferenceTime),
            })
            ->values();
    }

    public function isOverdue(ApprovalRequest $approvalRequest, ?CarbonInterface $referenceTime = null): bool
    {
        $resolvedReferenceTime = $referenceTime?->copy() ?? now();
        if ($this->hasCrossedReminderThreshold($approvalRequest, $resolvedReferenceTime)) {
            return true;
        }

        return $this->hasCrossedEscalationThreshold($approvalRequest, $resolvedReferenceTime);
    }

    public function isReminderDue(ApprovalRequest $approvalRequest, ?CarbonInterface $referenceTime = null): bool
    {
        $resolvedReferenceTime = $referenceTime?->copy() ?? now();

        return $approvalRequest->last_reminded_at === null
            && $this->hasCrossedReminderThreshold($approvalRequest, $resolvedReferenceTime);
    }

    public function isEscalationDue(ApprovalRequest $approvalRequest, ?CarbonInterface $referenceTime = null): bool
    {
        $resolvedReferenceTime = $referenceTime?->copy() ?? now();

        return $approvalRequest->escalated_at === null
            && $this->hasCrossedEscalationThreshold($approvalRequest, $resolvedReferenceTime);
    }

    private function hasCrossedReminderThreshold(ApprovalRequest $approvalRequest, CarbonInterface $referenceTime): bool
    {
        if (! $approvalRequest->rule instanceof ApprovalRule || ! is_numeric($approvalRequest->rule->reminder_after_minutes)) {
            return false;
        }

        $pendingSince = $this->pendingSince($approvalRequest);

        if (! $pendingSince instanceof CarbonInterface) {
            return false;
        }

        return $pendingSince->lte($referenceTime->copy()->subMinutes((int) $approvalRequest->rule->reminder_after_minutes));
    }

    private function hasCrossedEscalationThreshold(ApprovalRequest $approvalRequest, CarbonInterface $referenceTime): bool
    {
        if (! $approvalRequest->rule instanceof ApprovalRule || ! is_numeric($approvalRequest->rule->escalation_after_minutes)) {
            return false;
        }

        $pendingSince = $this->pendingSince($approvalRequest);

        if (! $pendingSince instanceof CarbonInterface) {
            return false;
        }

        return $pendingSince->lte($referenceTime->copy()->subMinutes((int) $approvalRequest->rule->escalation_after_minutes));
    }

    private function pendingSince(ApprovalRequest $approvalRequest): ?CarbonInterface
    {
        $pendingAssignment = $approvalRequest->assignments
            ->sortBy('created_at')
            ->first();

        return $pendingAssignment?->created_at ?? $approvalRequest->requested_at ?? $approvalRequest->created_at;
    }
}
