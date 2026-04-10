<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Approvals;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Models\ApprovalRequest;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

final class ApprovalRequestData extends Data
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $id,
        public string $action,
        public string $action_label,
        public string $status,
        public string $subject_name,
        public ?string $subject_type,
        public ?string $subject_id,
        public ?string $request_note,
        public ?string $review_note,
        public ?string $requested_by_id,
        public ?string $requested_by_name,
        public ?string $reviewed_by_id,
        public ?string $reviewed_by_name,
        public ?array $payload,
        public bool $can_approve,
        public bool $can_reject,
        public string $requested_at,
        public ?string $reviewed_at,
    ) {}

    public static function fromModel(ApprovalRequest $approvalRequest, ?User $actor = null): self
    {
        $approvalRequest->loadMissing('requester', 'reviewer', 'subject');

        $access = resolve(PermissionResourceAccess::class);
        $approvePermission = PermissionKey::tyanc('approvals', 'approve');
        $rejectPermission = PermissionKey::tyanc('approvals', 'reject');

        return new self(
            id: (string) $approvalRequest->id,
            action: (string) $approvalRequest->action,
            action_label: self::actionLabel($approvalRequest),
            status: (string) $approvalRequest->status,
            subject_name: self::subjectName($approvalRequest),
            subject_type: $approvalRequest->subject_type,
            subject_id: is_scalar($approvalRequest->subject_id) ? (string) $approvalRequest->subject_id : null,
            request_note: $approvalRequest->request_note,
            review_note: $approvalRequest->review_note,
            requested_by_id: $approvalRequest->requested_by_id,
            requested_by_name: $approvalRequest->requester instanceof User ? $approvalRequest->requester->name : null,
            reviewed_by_id: $approvalRequest->reviewed_by_id,
            reviewed_by_name: $approvalRequest->reviewer instanceof User ? $approvalRequest->reviewer->name : null,
            payload: is_array($approvalRequest->payload) ? $approvalRequest->payload : null,
            can_approve: $actor instanceof User
                && $approvalRequest->status === ApprovalRequest::StatusPending
                && $access->handle($actor, $approvePermission),
            can_reject: $actor instanceof User
                && $approvalRequest->status === ApprovalRequest::StatusPending
                && $access->handle($actor, $rejectPermission),
            requested_at: $approvalRequest->requested_at?->toIso8601String() ?? now()->toIso8601String(),
            reviewed_at: $approvalRequest->reviewed_at?->toIso8601String(),
        );
    }

    private static function actionLabel(ApprovalRequest $approvalRequest): string
    {
        $label = data_get($approvalRequest->payload, 'action_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return Str::of($approvalRequest->action)
            ->replace(['.', '_'], ' ')
            ->title()
            ->value();
    }

    private static function subjectName(ApprovalRequest $approvalRequest): string
    {
        $subjectLabel = data_get($approvalRequest->payload, 'subject_label');

        if (is_string($subjectLabel) && $subjectLabel !== '') {
            return $subjectLabel;
        }

        if ($approvalRequest->subject instanceof ImportRun) {
            return (string) ($approvalRequest->subject->file_name ?? __('Users import'));
        }

        return __('Approval request');
    }
}
