<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class NewApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ?ApprovalRequest $approvalRequest = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        $subjectLabel = is_string(data_get($this->approvalRequest?->payload, 'subject_label'))
            ? data_get($this->approvalRequest?->payload, 'subject_label')
            : null;

        return [
            'kind' => 'approval-request',
            'title' => __('New approval requested'),
            'body' => $subjectLabel !== null
                ? __('Approval required for :subject.', ['subject' => $subjectLabel])
                : __('A new approval request requires your review.'),
            'action_label' => __('Open request'),
            'action_url' => $this->approvalRequest instanceof ApprovalRequest
                ? route('cumpu.approvals.show', $this->approvalRequest, absolute: false)
                : route('cumpu.approvals.index', absolute: false),
        ];
    }
}
