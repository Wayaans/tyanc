<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Users;

use App\Data\Cumpu\Approvals\ApprovalContextRequestData;
use App\Models\ApprovalRequest;
use App\Models\UserUpdateDraft;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class UserUpdateDraftData extends Data
{
    /**
     * @param  array<int, string>  $changed_fields
     * @param  array<string, mixed>  $form_values
     */
    public function __construct(
        public string $id,
        public string $user_id,
        public string $created_by_id,
        public int $revision,
        public array $changed_fields,
        public array $form_values,
        public bool $has_password_change,
        public string $state,
        public bool $has_committable_draft,
        public bool $has_stale_subject_revision,
        public ?ApprovalContextRequestData $relevant_request,
        public ?string $committed_at,
        public string $updated_at,
    ) {}

    public static function fromModel(UserUpdateDraft $draft): self
    {
        $draft->loadMissing(['approvalRequests.requester', 'approvalRequests.consumedBy', 'approvalRequests.assignments.step']);

        $currentRevision = $draft->approvalSubjectRevision();
        /** @var ApprovalRequest|null $relevantRequest */
        $relevantRequest = $draft->approvalRequests
            ->where('subject_revision', $currentRevision)
            ->sortByDesc(fn (ApprovalRequest $approvalRequest): int => $approvalRequest->requested_at?->getTimestamp() ?? 0)
            ->first();

        $hasCommittableDraft = $relevantRequest instanceof ApprovalRequest
            && $relevantRequest->isGrantConsumable();
        $hasStaleSubjectRevision = $draft->approvalRequests
            ->contains(fn (ApprovalRequest $approvalRequest): bool => $approvalRequest->status === ApprovalRequest::StatusApproved
                && $approvalRequest->subject_revision !== null
                && $approvalRequest->subject_revision !== $currentRevision);

        return new self(
            id: (string) $draft->id,
            user_id: (string) $draft->user_id,
            created_by_id: (string) $draft->created_by_id,
            revision: (int) $draft->revision,
            changed_fields: $draft->changed_fields ?? [],
            form_values: $draft->formPayload(),
            has_password_change: $draft->hasPasswordChange(),
            state: self::state($draft, $relevantRequest),
            has_committable_draft: $hasCommittableDraft,
            has_stale_subject_revision: $hasStaleSubjectRevision,
            relevant_request: $relevantRequest instanceof ApprovalRequest
                ? ApprovalContextRequestData::fromModel($relevantRequest, true)
                : null,
            committed_at: $draft->committed_at?->toIso8601String(),
            updated_at: $draft->updated_at instanceof CarbonInterface
                ? $draft->updated_at->toIso8601String()
                : now()->toIso8601String(),
        );
    }

    private static function state(UserUpdateDraft $draft, ?ApprovalRequest $relevantRequest): string
    {
        if ($draft->committed_at !== null) {
            return 'committed';
        }

        if (! $relevantRequest instanceof ApprovalRequest) {
            return 'draft';
        }

        return match ($relevantRequest->effectiveStatus()) {
            ApprovalRequest::StatusPending, ApprovalRequest::StatusInReview => 'submitted_for_approval',
            ApprovalRequest::StatusApproved => 'approved_for_commit',
            ApprovalRequest::StatusRejected => 'rejected_for_revision',
            default => 'draft',
        };
    }
}
