<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Str;

trait FormatsApprovalNotificationPayload
{
    /**
     * @return array<string, string|null>
     */
    protected function approvalNotificationPayload(
        string $kind,
        string $title,
        string $body,
        ?ApprovalRequest $approvalRequest = null,
    ): array {
        $approvalRequest?->loadMissing(['requester', 'reviewer', 'consumedBy']);

        return [
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'action_label' => __('Open request'),
            'action_url' => $approvalRequest instanceof ApprovalRequest
                ? route('cumpu.approvals.show', $approvalRequest, absolute: false)
                : route('cumpu.approvals.index', absolute: false),
            'approval_id' => $approvalRequest?->id,
            'approval_status' => $approvalRequest?->effectiveStatus(),
            'permission_name' => $approvalRequest?->action,
            'action_name' => $approvalRequest instanceof ApprovalRequest
                ? $this->actionLabel($approvalRequest)
                : null,
            'subject_name' => $approvalRequest instanceof ApprovalRequest
                ? $this->subjectLabel($approvalRequest)
                : null,
            'requester_name' => $approvalRequest?->requester instanceof User
                ? $approvalRequest->requester->name
                : null,
            'reviewer_name' => $approvalRequest?->reviewer instanceof User
                ? $approvalRequest->reviewer->name
                : null,
            'consumed_by_name' => $approvalRequest?->consumedBy instanceof User
                ? $approvalRequest->consumedBy->name
                : null,
            'request_note' => $approvalRequest?->request_note,
            'review_note' => $approvalRequest?->review_note,
            'grant_expires_at' => $approvalRequest?->expires_at?->toIso8601String(),
            'consumed_at' => $approvalRequest?->consumed_at?->toIso8601String(),
        ];
    }

    protected function actionLabel(ApprovalRequest $approvalRequest): string
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

    protected function subjectLabel(ApprovalRequest $approvalRequest): string
    {
        $label = data_get($approvalRequest->payload, 'subject_label');

        return is_string($label) && $label !== ''
            ? $label
            : __('Approval request');
    }
}
