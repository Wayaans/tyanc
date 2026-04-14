<?php

declare(strict_types=1);

namespace App\Data\Cumpu\Approvals;

use App\Models\ApprovalAssignment;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRuleStep;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

final class ApprovalContextRequestData extends Data
{
    public function __construct(
        public string $id,
        public string $status,
        public string $mode,
        public string $action_label,
        public ?string $subject_revision,
        public bool $subject_revision_matches_subject,
        public ?string $requested_by_name,
        public ?string $current_step_label,
        public string $requested_at,
        public ?string $reviewed_at,
        public ?string $expires_at,
        public ?string $consumed_at,
        public ?string $consumed_by_name,
        public bool $is_grant_usable,
        public bool $is_grant_expired,
        public ?string $detail_url,
    ) {}

    public static function fromModel(ApprovalRequest $approvalRequest, bool $canViewDetails = false): self
    {
        $approvalRequest->loadMissing(['requester', 'consumedBy', 'assignments.step']);
        $currentAssignments = self::currentStepAssignments($approvalRequest->assignments);

        return new self(
            id: (string) $approvalRequest->id,
            status: $approvalRequest->effectiveStatus(),
            mode: $approvalRequest->approvalMode()->value,
            action_label: $canViewDetails
                ? self::actionLabel($approvalRequest)
                : (string) __('Approval request'),
            subject_revision: $canViewDetails ? $approvalRequest->subject_revision : null,
            subject_revision_matches_subject: $canViewDetails && $approvalRequest->subjectRevisionMatchesSubject(),
            requested_by_name: $canViewDetails && $approvalRequest->requester instanceof User
                ? $approvalRequest->requester->name
                : null,
            current_step_label: $canViewDetails
                ? self::currentStepLabel($currentAssignments)
                : null,
            requested_at: $approvalRequest->requested_at instanceof CarbonInterface
                ? $approvalRequest->requested_at->toIso8601String()
                : now()->toIso8601String(),
            reviewed_at: $canViewDetails && $approvalRequest->reviewed_at instanceof CarbonInterface
                ? $approvalRequest->reviewed_at->toIso8601String()
                : null,
            expires_at: $canViewDetails && $approvalRequest->expires_at instanceof CarbonInterface
                ? $approvalRequest->expires_at->toIso8601String()
                : null,
            consumed_at: $canViewDetails && $approvalRequest->consumed_at instanceof CarbonInterface
                ? $approvalRequest->consumed_at->toIso8601String()
                : null,
            consumed_by_name: $canViewDetails && $approvalRequest->consumedBy instanceof User
                ? $approvalRequest->consumedBy->name
                : null,
            is_grant_usable: $canViewDetails && $approvalRequest->isGrantConsumable(),
            is_grant_expired: $canViewDetails
                && ($approvalRequest->grantHasExpired() || $approvalRequest->effectiveStatus() === ApprovalRequest::StatusExpired),
            detail_url: $canViewDetails
                ? route('cumpu.approvals.show', $approvalRequest, absolute: false)
                : null,
        );
    }

    private static function actionLabel(ApprovalRequest $approvalRequest): string
    {
        $label = data_get($approvalRequest->payload, 'action_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of((string) $approvalRequest->action)
            ->replace(['.', '_'], ' ')
            ->title()
            ->value();
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     * @return Collection<int, ApprovalAssignment>
     */
    private static function currentStepAssignments(Collection $assignments): Collection
    {
        $pendingAssignments = $assignments
            ->filter(fn (ApprovalAssignment $assignment): bool => $assignment->status === ApprovalAssignment::StatusPending)
            ->values();

        $currentStepOrder = $pendingAssignments
            ->map(function (ApprovalAssignment $assignment): ?int {
                if ($assignment->step_order_snapshot !== null) {
                    return (int) $assignment->step_order_snapshot;
                }

                $step = $assignment->step;

                return $step instanceof ApprovalRuleStep
                    ? $step->step_order
                    : null;
            })
            ->filter(fn (mixed $stepOrder): bool => is_numeric($stepOrder))
            ->sort()
            ->first();

        if ($currentStepOrder === null) {
            return collect();
        }

        return $pendingAssignments
            ->filter(function (ApprovalAssignment $assignment) use ($currentStepOrder): bool {
                if ($assignment->step_order_snapshot !== null) {
                    return (int) $assignment->step_order_snapshot === $currentStepOrder;
                }

                $step = $assignment->step;

                return $step instanceof ApprovalRuleStep
                    && $step->step_order === $currentStepOrder;
            })
            ->values();
    }

    /**
     * @param  Collection<int, ApprovalAssignment>  $assignments
     */
    private static function currentStepLabel(Collection $assignments): ?string
    {
        /** @var ApprovalAssignment|null $assignment */
        $assignment = $assignments->first();

        if (! $assignment instanceof ApprovalAssignment) {
            return null;
        }

        if (is_string($assignment->step_label_snapshot) && $assignment->step_label_snapshot !== '') {
            return $assignment->step_label_snapshot;
        }

        return $assignment->step instanceof ApprovalRuleStep
            ? $assignment->step->label
            : null;
    }
}
